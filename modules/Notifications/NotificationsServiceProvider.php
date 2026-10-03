<?php

namespace Modules\Notifications;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Notifications\DatabaseNotification;
use Modules\Notifications\Services\DatabaseNotificationPreferences;
use Modules\Shared\Contracts\NotificationPreferences;
use Modules\Shared\Support\ModuleServiceProvider;

class NotificationsServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Notifications';
    }

    protected function contracts(): array
    {
        return [NotificationPreferences::class => DatabaseNotificationPreferences::class];
    }

    protected function schedule(Schedule $schedule): void
    {
        // Keep read notifications for 90 days.
        $schedule->call(fn () => DatabaseNotification::query()
            ->whereNotNull('read_at')
            ->where('created_at', '<', now()->subDays(90))
            ->delete())->daily()->name('notifications:prune');
    }
}
