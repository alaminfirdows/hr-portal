<?php

namespace Modules\Documents;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Documents\Console\NotifyExpiringDocuments;
use Modules\Documents\Database\Seeders\DocumentsDemoSeeder;
use Modules\Documents\Widgets\ExpiringDocumentsWidget;
use Modules\Documents\Widgets\RecentDocumentsWidget;
use Modules\Shared\Support\ModuleServiceProvider;
use Modules\Shared\Support\NavItem;

class DocumentsServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Documents';
    }

    public function seedOrder(): int
    {
        return 60;
    }

    public function permissions(): array
    {
        return [
            'documents.view' => 'View documents shared with you',
            'documents.upload' => 'Upload documents and add links',
            'documents.manage' => 'Manage every document and folder',
        ];
    }

    public function navigation(): array
    {
        return [
            new NavItem('Documents', 'documents.index', 'folders', 'documents.view', 'Collaboration', 600, 'documents.*'),
        ];
    }

    public function dashboardWidgets(): array
    {
        return [RecentDocumentsWidget::class, ExpiringDocumentsWidget::class];
    }

    public function demoSeeders(): array
    {
        return [DocumentsDemoSeeder::class];
    }

    public function notificationTypes(): array
    {
        return [
            'documents.shared' => 'A document was shared with you',
            'documents.expiring' => 'A document is about to expire',
        ];
    }

    protected function consoleCommands(): array
    {
        return [NotifyExpiringDocuments::class];
    }

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('documents:notify-expiring')->dailyAt('07:00');
    }
}
