<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Middleware;
use Modules\Identity\Models\User;
use Modules\Notifications\Http\Controllers\NotificationController;
use Modules\Shared\Contracts\OrgHierarchy;
use Modules\Shared\Support\ModuleRegistry;
use Modules\Shared\Support\NavItem;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'employee_id' => fn () => $user ? app(OrgHierarchy::class)->employeeIdForUser($user->id) : null,
                'is_super' => fn () => (bool) $user?->isSuper(),
                'permissions' => fn () => $user ? $this->permissions($user) : [],
            ],
            'navigation' => fn () => $user ? $this->navigation($user, $request) : [],
            'notifications' => fn () => $user ? [
                'unread' => $user->unreadNotifications()->count(),
                'recent' => $user->notifications()->limit(6)->get()->map(NotificationController::present(...)),
            ] : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Permission key => scope. Super users get every key at "all".
     *
     * @return array<string, string>
     */
    private function permissions(User $user): array
    {
        if ($user->isSuper()) {
            return array_map(fn () => 'all', app(ModuleRegistry::class)->permissions());
        }

        return array_map(fn ($scope) => $scope->value, $user->permissionScopes());
    }

    /**
     * Sidebar groups, filtered by permission, with resolved URLs and active state.
     *
     * @return list<array{label: string, items: list<array{title: string, href: string, icon: string, active: bool}>}>
     */
    private function navigation(User $user, Request $request): array
    {
        $current = Route::currentRouteName() ?? '';
        $keys = $this->permissions($user);
        $groups = [];

        foreach (app(ModuleRegistry::class)->navigation() as $item) {
            if (! Route::has($item->route) || ! $this->allowed($item, $keys)) {
                continue;
            }

            $patterns = explode('|', $item->activePattern ?? $item->route);

            $groups[$item->group][] = [
                'title' => __($item->label),
                'href' => route($item->route, absolute: false),
                'icon' => $item->icon,
                'active' => Str::is($patterns, $current),
            ];
        }

        $order = ['General', 'People', 'Time off', 'Attendance', 'Payroll', 'Work', 'Company', 'Organization', 'Reports', 'Administration'];
        $rank = fn (string $group): int => ($pos = array_search($group, $order, true)) === false ? 50 : (int) $pos;
        uksort($groups, fn (string $a, string $b) => $rank($a) <=> $rank($b));

        return array_map(
            fn (string $label, array $items) => ['label' => __($label), 'items' => $items],
            array_keys($groups),
            array_values($groups),
        );
    }

    /**
     * @param  array<string, string>  $keys
     */
    private function allowed(NavItem $item, array $keys): bool
    {
        $required = $item->permissions();

        return $required === [] || array_intersect($required, array_keys($keys)) !== [];
    }
}
