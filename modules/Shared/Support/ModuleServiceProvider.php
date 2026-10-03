<?php

namespace Modules\Shared\Support;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Modules\Shared\Contracts\DashboardWidget;
use ReflectionClass;

/**
 * Base provider every module extends. Wires migrations, routes, pages, listeners,
 * schedules and the module manifest without any central registration.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Middleware applied to the module's authenticated routes (Routes/web.php).
     *
     * @var list<string>
     */
    protected array $routeMiddleware = ['web', 'auth', 'verified', 'active'];

    abstract public function name(): string;

    public function slug(): string
    {
        return Str::kebab($this->name());
    }

    public function path(string $path = ''): string
    {
        $base = dirname((string) (new ReflectionClass(static::class))->getFileName());

        return $path === '' ? $base : $base.DIRECTORY_SEPARATOR.$path;
    }

    /**
     * @return array<string, string>
     */
    public function permissions(): array
    {
        return [];
    }

    /**
     * @return list<NavItem>
     */
    public function navigation(): array
    {
        return [];
    }

    /**
     * @return list<class-string<DashboardWidget>>
     */
    public function dashboardWidgets(): array
    {
        return [];
    }

    /**
     * @return list<class-string>
     */
    public function seeders(): array
    {
        return [];
    }

    /**
     * @return list<class-string>
     */
    public function demoSeeders(): array
    {
        return [];
    }

    /**
     * Seeders run in ascending order; modules that others depend on use lower numbers.
     */
    public function seedOrder(): int
    {
        return 100;
    }

    /**
     * Notification types this module sends, for user preferences.
     *
     * @return array<string, string>
     */
    public function notificationTypes(): array
    {
        return [];
    }

    /**
     * @return array<class-string, list<class-string>>
     */
    protected function listeners(): array
    {
        return [];
    }

    /**
     * @return list<class-string>
     */
    protected function consoleCommands(): array
    {
        return [];
    }

    /**
     * Contract => implementation singletons.
     *
     * @return array<class-string, class-string>
     */
    protected function contracts(): array
    {
        return [];
    }

    protected function schedule(Schedule $schedule): void
    {
        //
    }

    public function register(): void
    {
        foreach ($this->contracts() as $contract => $implementation) {
            $this->app->singleton($contract, $implementation);
        }

        $this->app->make(ModuleRegistry::class)->register(new ModuleManifest(
            name: $this->name(),
            slug: $this->slug(),
            path: $this->path(),
            permissions: $this->permissions(),
            navigation: $this->navigation(),
            widgets: $this->dashboardWidgets(),
            seeders: $this->seeders(),
            demoSeeders: $this->demoSeeders(),
            notificationTypes: $this->notificationTypes(),
            seedOrder: $this->seedOrder(),
        ));
    }

    public function boot(): void
    {
        if (is_dir($this->path('Database/Migrations'))) {
            $this->loadMigrationsFrom($this->path('Database/Migrations'));
        }

        if (is_dir($this->path('resources/views'))) {
            $this->loadViewsFrom($this->path('resources/views'), $this->slug());
        }

        $this->registerRoutes();
        $this->registerPages();

        foreach ($this->listeners() as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }

        if ($this->app->runningInConsole()) {
            $this->commands($this->consoleCommands());
        }

        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $this->schedule($schedule));
    }

    protected function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        if (file_exists($this->path('Routes/public.php'))) {
            Route::middleware('web')->group($this->path('Routes/public.php'));
        }

        if (file_exists($this->path('Routes/web.php'))) {
            Route::middleware($this->routeMiddleware)->group($this->path('Routes/web.php'));
        }
    }

    protected function registerPages(): void
    {
        $pages = $this->path('resources/js/pages');

        if (! is_dir($pages)) {
            return;
        }

        /** @var list<string> $paths */
        $paths = config('inertia.pages.paths', []);

        if (! in_array($pages, $paths, true)) {
            config(['inertia.pages.paths' => [...$paths, $pages]]);
        }
    }
}
