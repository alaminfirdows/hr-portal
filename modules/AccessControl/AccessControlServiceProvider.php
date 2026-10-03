<?php

namespace Modules\AccessControl;

use Illuminate\Support\Facades\Gate;
use Modules\AccessControl\Console\SyncPermissionsCommand;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Services\Access;
use Modules\Identity\Models\User;
use Modules\Shared\Support\ModuleRegistry;
use Modules\Shared\Support\ModuleServiceProvider;
use Modules\Shared\Support\NavItem;

class AccessControlServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'AccessControl';
    }

    public function seedOrder(): int
    {
        return 10;
    }

    public function slug(): string
    {
        return 'access';
    }

    public function permissions(): array
    {
        return [
            'roles.manage' => 'Manage roles, permissions and role assignments',
        ];
    }

    public function navigation(): array
    {
        return [
            new NavItem('Roles & permissions', 'access.roles.index', 'shield-check', 'roles.manage', 'Administration', 910),
        ];
    }

    public function seeders(): array
    {
        return [AccessControlSeeder::class];
    }

    protected function consoleCommands(): array
    {
        return [SyncPermissionsCommand::class];
    }

    protected function contracts(): array
    {
        return [Access::class => Access::class];
    }

    public function boot(): void
    {
        parent::boot();

        Gate::before(function (mixed $user, string $ability, array $arguments): ?bool {
            if (! $user instanceof User) {
                return null;
            }

            if ($user->isSuper()) {
                return true;
            }

            if (array_key_exists($ability, $this->app->make(ModuleRegistry::class)->permissions())) {
                return $this->app->make(Access::class)->can($user, $ability, $arguments[0] ?? null);
            }

            return null;
        });
    }
}
