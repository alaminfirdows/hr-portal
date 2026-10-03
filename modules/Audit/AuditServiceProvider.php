<?php

namespace Modules\Audit;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Modules\Audit\Console\PruneAuditLogsCommand;
use Modules\Audit\Listeners\WriteAuditLog;
use Modules\Audit\Services\EloquentAuditTrail;
use Modules\Shared\Contracts\AuditableEvent;
use Modules\Shared\Contracts\AuditTrail;
use Modules\Shared\Support\ModuleServiceProvider;
use Modules\Shared\Support\NavItem;

class AuditServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Audit';
    }

    public function permissions(): array
    {
        return ['audit.view' => 'View the audit log'];
    }

    public function navigation(): array
    {
        return [new NavItem('Audit log', 'audit.index', 'scroll-text', 'audit.view', 'Administration', 930)];
    }

    protected function contracts(): array
    {
        return [AuditTrail::class => EloquentAuditTrail::class];
    }

    protected function consoleCommands(): array
    {
        return [PruneAuditLogsCommand::class];
    }

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('audit:prune')->weekly();
    }

    public function boot(): void
    {
        parent::boot();

        // Cheap type check first; only auditable events resolve the logger.
        Event::listen('*', function (string $name, array $payload): void {
            if (($payload[0] ?? null) instanceof AuditableEvent) {
                $this->app->make(WriteAuditLog::class)->handle($name, $payload);
            }
        });
    }
}
