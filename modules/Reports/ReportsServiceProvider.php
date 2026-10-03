<?php

namespace Modules\Reports;

use Modules\Reports\Contracts\Report;
use Modules\Reports\Reports\Attendance\AttendanceSummaryReport;
use Modules\Reports\Reports\Attendance\LateArrivalsReport;
use Modules\Reports\Reports\Hr\HeadcountReport;
use Modules\Reports\Reports\Hr\MovementsReport;
use Modules\Reports\Reports\Hr\TurnoverReport;
use Modules\Reports\Reports\Leave\LeaveBalancesReport;
use Modules\Reports\Reports\Leave\LeaveUsageReport;
use Modules\Reports\Reports\Payroll\PayrollComponentsReport;
use Modules\Reports\Reports\Payroll\SalaryDistributionReport;
use Modules\Reports\Reports\Projects\ActiveProjectsReport;
use Modules\Reports\Reports\Projects\WorkloadReport;
use Modules\Reports\Services\ReportRegistry;
use Modules\Shared\Support\ModuleServiceProvider;
use Modules\Shared\Support\NavItem;

class ReportsServiceProvider extends ModuleServiceProvider
{
    /**
     * @var list<class-string<Report>>
     */
    public const REPORTS = [
        HeadcountReport::class,
        MovementsReport::class,
        TurnoverReport::class,
        AttendanceSummaryReport::class,
        LateArrivalsReport::class,
        LeaveUsageReport::class,
        LeaveBalancesReport::class,
        PayrollComponentsReport::class,
        SalaryDistributionReport::class,
        ActiveProjectsReport::class,
        WorkloadReport::class,
    ];

    public function name(): string
    {
        return 'Reports';
    }

    public function seedOrder(): int
    {
        return 200;
    }

    public function permissions(): array
    {
        return ['reports.view' => 'View and export reports'];
    }

    public function navigation(): array
    {
        return [new NavItem('Reports', 'reports.index', 'bar-chart-3', 'reports.view', 'Insights', 800, 'reports.*')];
    }

    public function register(): void
    {
        $this->app->singleton(ReportRegistry::class, fn ($app) => new ReportRegistry($app, self::REPORTS));

        parent::register();
    }
}
