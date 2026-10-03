<?php

namespace Modules\Dashboard;

use Modules\Dashboard\Widgets\UpcomingHolidaysWidget;
use Modules\Shared\Support\ModuleServiceProvider;
use Modules\Shared\Support\NavItem;

class DashboardServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Dashboard';
    }

    public function navigation(): array
    {
        return [new NavItem('Dashboard', 'dashboard', 'layout-grid', null, 'General', 0)];
    }

    public function dashboardWidgets(): array
    {
        return [UpcomingHolidaysWidget::class];
    }
}
