<?php

namespace Modules\Payroll;

use Modules\Payroll\Database\Seeders\PayrollDemoSeeder;
use Modules\Payroll\Database\Seeders\PayrollSetupSeeder;
use Modules\Payroll\Widgets\LatestPayslipWidget;
use Modules\Payroll\Widgets\PayrollStatusWidget;
use Modules\Shared\Support\ModuleServiceProvider;
use Modules\Shared\Support\NavItem;

class PayrollServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Payroll';
    }

    public function seedOrder(): int
    {
        return 80;
    }

    public function permissions(): array
    {
        return [
            'payroll.view' => 'View payslips',
            'payroll.manage' => 'Manage salaries, pay components and payroll runs',
            'payroll.approve' => 'Approve payroll runs',
        ];
    }

    public function navigation(): array
    {
        return [
            new NavItem('My payslips', 'payroll.payslips.index', 'receipt', 'payroll.view', 'Payroll', 500, 'payroll.payslips.*'),
            new NavItem('Payroll runs', 'payroll.runs.index', 'banknote', 'payroll.manage', 'Payroll', 510, 'payroll.runs.*'),
            new NavItem('Salaries', 'payroll.employees.index', 'wallet', 'payroll.manage', 'Payroll', 520, 'payroll.employees.*'),
            new NavItem('Pay components', 'payroll.components.index', 'calculator', 'payroll.manage', 'Payroll', 530, 'payroll.components.*'),
            new NavItem('Tax slabs', 'payroll.tax-slabs.index', 'calculator', 'payroll.manage', 'Payroll', 540, 'payroll.tax-slabs.*'),
        ];
    }

    public function dashboardWidgets(): array
    {
        return [LatestPayslipWidget::class, PayrollStatusWidget::class];
    }

    public function notificationTypes(): array
    {
        return ['payroll.payslip_available' => 'A new payslip is available'];
    }

    public function seeders(): array
    {
        return [PayrollSetupSeeder::class];
    }

    public function demoSeeders(): array
    {
        return [PayrollDemoSeeder::class];
    }
}
