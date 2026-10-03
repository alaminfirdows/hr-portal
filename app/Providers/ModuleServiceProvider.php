<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Shared\SharedServiceProvider;
use Modules\Shared\Support\ModuleRegistry;

/**
 * Discovers and registers every module provider in modules/*. Shared goes first.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class);

        foreach ($this->discover() as $provider) {
            $this->app->register($provider);
        }
    }

    /**
     * @return list<class-string<ServiceProvider>>
     */
    private function discover(): array
    {
        $providers = [SharedServiceProvider::class];

        foreach (glob(base_path('modules/*/*ServiceProvider.php')) ?: [] as $file) {
            $module = basename(dirname($file));
            $class = 'Modules\\'.$module.'\\'.basename($file, '.php');

            if ($class !== SharedServiceProvider::class && is_subclass_of($class, ServiceProvider::class)) {
                $providers[] = $class;
            }
        }

        return $providers;
    }
}
