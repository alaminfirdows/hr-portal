<?php

namespace Modules\Announcements;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Announcements\Console\DispatchAnnouncements;
use Modules\Announcements\Database\Seeders\AnnouncementsDemoSeeder;
use Modules\Announcements\Widgets\RecentAnnouncementsWidget;
use Modules\Shared\Support\ModuleServiceProvider;
use Modules\Shared\Support\NavItem;

class AnnouncementsServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Announcements';
    }

    public function seedOrder(): int
    {
        return 61;
    }

    public function permissions(): array
    {
        return [
            'announcements.view' => 'Read announcements addressed to you',
            'announcements.manage' => 'Create, schedule and delete announcements',
        ];
    }

    public function navigation(): array
    {
        return [
            new NavItem('Announcements', 'announcements.index', 'megaphone', 'announcements.view', 'Collaboration', 610, 'announcements.index|announcements.show'),
            new NavItem('Manage announcements', 'announcements.manage', 'scroll-text', 'announcements.manage', 'Collaboration', 620, 'announcements.manage'),
        ];
    }

    public function dashboardWidgets(): array
    {
        return [RecentAnnouncementsWidget::class];
    }

    public function demoSeeders(): array
    {
        return [AnnouncementsDemoSeeder::class];
    }

    public function notificationTypes(): array
    {
        return ['announcements.published' => 'A new announcement was published'];
    }

    protected function consoleCommands(): array
    {
        return [DispatchAnnouncements::class];
    }

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('announcements:dispatch')->everyMinute()->withoutOverlapping();
    }
}
