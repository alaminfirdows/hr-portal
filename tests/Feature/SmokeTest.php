<?php

use Database\Seeders\DemoSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Modules\Identity\Models\User;
use Modules\Shared\Contracts\DashboardWidget;
use Modules\Shared\Support\ModuleRegistry;

/*
|--------------------------------------------------------------------------
| Smoke tests: seeded demo data, every default role, every GET page
|--------------------------------------------------------------------------
*/

const SMOKE_ROLES = ['admin', 'hr', 'manager', 'project_manager', 'employee', 'support'];

/** Routes that stream files, redirect, or are auth/guest plumbing. */
function smokeSkipped(RoutingRoute $route): bool
{
    $name = (string) $route->getName();

    if ($name === '' || Str::startsWith($name, ['verification.', 'password.', 'invitations.', 'two-factor.', 'passkey', 'well-known', 'login', 'home', 'storage.'])) {
        return true;
    }

    if (Str::endsWith($name, ['.download', '.export', '.pdf', '.photo', '.attachment', '.open', '.qr-code', '.secret-key', '.recovery-codes'])) {
        return true;
    }

    return collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && (Str::startsWith($m, 'signed') || $m === 'guest' || Str::startsWith($m, 'throttle:login')));
}

/**
 * @return array<string, mixed>|null route parameters resolved from seeded models, null when one cannot be resolved
 */
function smokeParameters(RoutingRoute $route): ?array
{
    $params = [];

    foreach ($route->signatureParameters(['subClass' => Model::class]) as $parameter) {
        $type = $parameter->getType();
        $class = $type instanceof ReflectionNamedType ? $type->getName() : null;

        if ($class === null || ! is_subclass_of($class, Model::class) || ! in_array($parameter->getName(), $route->parameterNames(), true)) {
            continue;
        }

        $model = $class::query()->orderBy('id')->first();

        if ($model === null) {
            return null;
        }

        $params[$parameter->getName()] = $model;
    }

    return count($params) === count($route->parameterNames()) ? $params : null;
}

function smokePageExists(string $component): bool
{
    $files = ["resources/js/pages/{$component}.tsx", ...collect(File::directories(base_path('modules')))->map(fn (string $dir) => 'modules/'.basename($dir)."/resources/js/pages/{$component}.tsx")->all()];

    return collect($files)->contains(fn (string $file) => File::exists(base_path($file)));
}

test('every named GET page renders for every default role', function () {
    config(['app.seed_demo' => false]);
    $this->seed();
    $this->seed(DemoSeeder::class);
    $users = collect(['admin' => 'admin@example.com', 'hr' => 'hr@example.com', 'manager' => 'manager@example.com', 'project_manager' => 'pm@example.com', 'employee' => 'employee@example.com'])
        ->map(fn (string $email) => User::query()->where('email', $email)->firstOrFail());
    $users['support'] = userWithRole('support');

    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $r) => in_array('GET', $r->methods(), true) && ! smokeSkipped($r))
        ->values();

    expect($routes->count())->toBeGreaterThan(40);

    $failures = [];
    $forbidden = [];

    foreach ($users as $role => $user) {
        foreach ($routes as $route) {
            $parameters = [];

            if ($route->parameterNames() !== []) {
                $parameters = smokeParameters($route);

                if ($parameters === null) {
                    continue;
                }
            }

            $url = route($route->getName(), $parameters);
            $response = $this->actingAs($user)->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => (string) Inertia::getVersion()])->get($url);
            $status = $response->getStatusCode();
            $label = "{$role} GET {$route->getName()} ({$url})";

            if ($status >= 500) {
                $failures[] = "{$label} => {$status}";
            } elseif ($status === 200 && $response->headers->has('X-Inertia')) {
                $component = (string) $response->json('component');

                if (! smokePageExists($component)) {
                    $failures[] = "{$label} => missing page component {$component}";
                }
            } elseif ($status === 403) {
                $forbidden[] = $label;
            } elseif (! in_array($status, [200, 302, 303, 404], true)) {
                $failures[] = "{$label} => {$status}";
            }
        }
    }

    expect($failures)->toBe([]);

    // Admin bypasses every permission check: it must never see a 403.
    expect(collect($forbidden)->filter(fn (string $l) => Str::startsWith($l, 'admin ')))->toBeEmpty();

    // HR holds wildcard grants for every business module; only admin-only areas may be forbidden.
    $unexpected = collect($forbidden)
        ->filter(fn (string $l) => Str::startsWith($l, 'hr '))
        ->reject(fn (string $l) => Str::contains($l, ['access.roles', 'access.permissions']));
    expect($unexpected->values()->all())->toBe([]);
});

test('dashboard widgets never throw and fit the role', function () {
    config(['app.seed_demo' => false]);
    $this->seed();
    $this->seed(DemoSeeder::class);

    $users = [
        'admin' => User::query()->where('email', 'admin@example.com')->firstOrFail(),
        'hr' => User::query()->where('email', 'hr@example.com')->firstOrFail(),
        'manager' => User::query()->where('email', 'manager@example.com')->firstOrFail(),
        'project_manager' => User::query()->where('email', 'pm@example.com')->firstOrFail(),
        'employee' => User::query()->where('email', 'employee@example.com')->firstOrFail(),
        'support' => userWithRole('support'),
    ];

    $expected = [
        'admin' => ['employees.headcount', 'payroll.status', 'leave.pending_approvals', 'projects.active'],
        'hr' => ['employees.headcount', 'payroll.status', 'payroll.latest_payslip', 'leave.my_balances', 'leave.pending_approvals', 'attendance.my_today', 'attendance.team_today', 'announcements.recent', 'projects.active', 'documents.expiring'],
        'manager' => ['leave.my_balances', 'leave.pending_approvals', 'attendance.my_today', 'attendance.team_today', 'employees.my_team', 'announcements.recent', 'projects.mine'],
        'project_manager' => ['leave.my_balances', 'attendance.my_today', 'announcements.recent', 'projects.active', 'projects.mine'],
        'employee' => ['leave.my_balances', 'attendance.my_today', 'payroll.latest_payslip', 'announcements.recent', 'projects.mine', 'documents.recent'],
        'support' => ['announcements.recent', 'documents.recent'],
    ];
    $forbidden = [
        'manager' => ['employees.headcount', 'payroll.status', 'projects.active'],
        'project_manager' => ['employees.headcount', 'payroll.status', 'leave.pending_approvals', 'attendance.team_today'],
        'employee' => ['employees.headcount', 'payroll.status', 'projects.active', 'leave.pending_approvals', 'attendance.team_today', 'attendance.late_today', 'documents.expiring'],
        'support' => ['payroll.status', 'leave.my_balances', 'attendance.my_today', 'leave.pending_approvals', 'projects.mine'],
    ];

    $registry = app(ModuleRegistry::class);

    foreach ($users as $role => $user) {
        foreach ($registry->widgets() as $class) {
            /** @var DashboardWidget $widget */
            $widget = app($class);

            if ($widget->visible($user)) {
                expect(fn () => $widget->data($user))->not->toThrow(Throwable::class, "{$role}: {$class}");
            }
        }

        $keys = $this->actingAs($user)->get(route('dashboard'))->assertOk()->viewData('page')['props']['widgets'];
        $keys = collect($keys)->pluck('key')->all();

        expect(array_values(array_diff($expected[$role] ?? [], $keys)))->toBe([], "{$role} is missing widgets");
        expect(array_values(array_intersect($forbidden[$role] ?? [], $keys)))->toBe([], "{$role} sees forbidden widgets");
    }
});

test('sidebar navigation only links pages the role may open', function () {
    config(['app.seed_demo' => false]);
    $this->seed();
    $this->seed(DemoSeeder::class);

    $users = [
        'admin' => User::query()->where('email', 'admin@example.com')->firstOrFail(),
        'hr' => User::query()->where('email', 'hr@example.com')->firstOrFail(),
        'manager' => User::query()->where('email', 'manager@example.com')->firstOrFail(),
        'project_manager' => User::query()->where('email', 'pm@example.com')->firstOrFail(),
        'employee' => User::query()->where('email', 'employee@example.com')->firstOrFail(),
        'support' => userWithRole('support'),
    ];

    $bad = [];

    foreach ($users as $role => $user) {
        $groups = $this->actingAs($user)->get(route('dashboard'))->assertOk()->viewData('page')['props']['navigation'];

        expect($groups)->not->toBeEmpty();

        foreach ($groups as $group) {
            foreach ($group['items'] as $item) {
                $status = $this->actingAs($user)->get($item['href'])->getStatusCode();

                if ($status !== 200) {
                    $bad[] = "{$role}: {$group['label']} / {$item['title']} ({$item['href']}) => {$status}";
                }
            }
        }
    }

    expect($bad)->toBe([]);
});

test('file and export endpoints do not error for admin', function () {
    config(['app.seed_demo' => false]);
    $this->seed();
    $this->seed(DemoSeeder::class);
    $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

    $bad = [];
    $visited = 0;

    foreach (Route::getRoutes()->getRoutes() as $route) {
        $name = (string) $route->getName();

        if (! in_array('GET', $route->methods(), true) || ! Str::endsWith($name, ['.download', '.export', '.pdf', '.photo', '.attachment'])) {
            continue;
        }

        $parameters = smokeParameters($route);

        if ($parameters === null) {
            continue;
        }

        $visited++;
        $status = $this->actingAs($admin)->get(route($name, $parameters))->getStatusCode();

        if ($status >= 500) {
            $bad[] = "{$name} => {$status}";
        }
    }

    expect($visited)->toBeGreaterThan(0)->and($bad)->toBe([]);
});

test('every navigation icon exists in the dynamic icon map', function () {
    $source = (string) file_get_contents(resource_path('js/components/portal/dynamic-icon.tsx'));
    $missing = collect(app(ModuleRegistry::class)->navigation())->pluck('icon')->unique()->reject(fn (string $icon) => str_contains($source, "'{$icon}':") || preg_match('/^\\s+'.preg_quote($icon, '/').':/m', $source) === 1)->values()->all();

    expect($missing)->toBe([]);
});
