<?php

namespace Modules\Payroll\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;
use Modules\Payroll\Actions\ApprovePayrollRun;
use Modules\Payroll\Actions\CalculatePayrollRun;
use Modules\Payroll\Actions\CreatePayrollRun;
use Modules\Payroll\Actions\MarkReviewed;
use Modules\Payroll\Actions\ProcessPayrollRun;
use Modules\Payroll\Enums\CalculationMethod;
use Modules\Payroll\Enums\ComponentType;
use Modules\Payroll\Models\Compensation;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\TaxSlab;

/**
 * Demo pay components, monthly tax slabs, a salary for every employee and one processed run for last month.
 * Amounts are minor units; percentages are basis points.
 */
class PayrollDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->components();
        $this->slabs();
        $this->compensations();
        $this->lastMonthRun();
    }

    private function components(): void
    {
        $rows = [
            ['HRA', 'House rent', ComponentType::Earning, CalculationMethod::PercentOfBasic, 5000, null, true, 10],
            ['MED', 'Medical', ComponentType::Earning, CalculationMethod::Fixed, 25000, null, true, 20],
            ['TRN', 'Transport', ComponentType::Earning, CalculationMethod::Fixed, 15000, null, true, 30],
            ['OT', 'Overtime', ComponentType::Earning, CalculationMethod::Formula, null, 'overtime_hours * basic / 208 * 1.5', true, 40],
            ['PF', 'Provident fund', ComponentType::Deduction, CalculationMethod::PercentOfBasic, 1000, null, false, 100],
            ['TAX', 'Income tax', ComponentType::Deduction, CalculationMethod::TaxSlab, null, null, false, 110],
        ];

        foreach ($rows as [$code, $name, $type, $calculation, $value, $formula, $taxable, $sort]) {
            PayComponent::query()->firstOrCreate(['code' => $code], [
                'name' => $name,
                'type' => $type,
                'calculation' => $calculation,
                'value' => $value,
                'formula' => $formula,
                'is_taxable' => $taxable,
                'applies_to_all' => true,
                'is_active' => true,
                'sort' => $sort,
            ]);
        }
    }

    private function slabs(): void
    {
        if (TaxSlab::query()->exists()) {
            return;
        }

        foreach ([[0, 300000, 0], [300000, 600000, 500], [600000, 1000000, 1000], [1000000, null, 1500]] as [$min, $max, $rate]) {
            TaxSlab::query()->create(['min_amount' => $min, 'max_amount' => $max, 'rate_bp' => $rate]);
        }
    }

    private function compensations(): void
    {
        $actor = User::query()->where('email', 'hr@example.com')->value('id');

        Employee::query()->orderBy('id')->each(function (Employee $employee) use ($actor): void {
            if (Compensation::query()->where('employee_id', $employee->id)->exists()) {
                return;
            }

            Compensation::query()->create([
                'employee_id' => $employee->id,
                'basic_salary' => (400 + (($employee->id * 37) % 13) * 50) * 100 * 10,
                'effective_from' => $employee->joined_on,
                'effective_to' => null,
                'reason' => 'Initial salary',
                'created_by' => $actor,
            ]);
        });
    }

    private function lastMonthRun(): void
    {
        $period = now()->subMonth()->format('Y-m');
        $hr = User::query()->where('email', 'hr@example.com')->first();
        $admin = User::query()->where('email', 'admin@example.com')->first();

        if ($hr === null || $admin === null || PayrollRun::query()->where('period', $period)->exists()) {
            return;
        }

        $run = app(CreatePayrollRun::class)->handle($period, $hr);
        app(CalculatePayrollRun::class)->handle($run, $hr);
        app(MarkReviewed::class)->handle($run->refresh(), $hr);
        app(ApprovePayrollRun::class)->handle($run->refresh(), $admin);
        app(ProcessPayrollRun::class)->handle($run->refresh(), $admin);
    }
}
