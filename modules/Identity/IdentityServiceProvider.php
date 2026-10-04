<?php

namespace Modules\Identity;

use Illuminate\Auth\Events\Login;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Modules\Identity\Console\PruneInvitationsCommand;
use Modules\Identity\Database\Seeders\InitialAdminSeeder;
use Modules\Identity\Http\Middleware\EnsureUserIsActive;
use Modules\Identity\Listeners\RecordLastLogin;
use Modules\Shared\Support\ModuleServiceProvider;
use Modules\Shared\Support\NavItem;

class IdentityServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Identity';
    }

    public function seedOrder(): int
    {
        return 15;
    }

    public function seeders(): array
    {
        return [InitialAdminSeeder::class];
    }

    public function permissions(): array
    {
        return [
            'users.manage' => 'Invite, activate and deactivate user accounts',
        ];
    }

    public function navigation(): array
    {
        return [
            new NavItem('Users', 'identity.users.index', 'user-cog', 'users.manage', 'Administration', 900),
        ];
    }

    protected function listeners(): array
    {
        return [
            Login::class => [RecordLastLogin::class],
        ];
    }

    protected function consoleCommands(): array
    {
        return [PruneInvitationsCommand::class];
    }

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('invitations:prune')->daily();
    }

    public function boot(): void
    {
        $this->app->make(Router::class)->aliasMiddleware('active', EnsureUserIsActive::class);

        parent::boot();
    }
}
