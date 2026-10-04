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
use Modules\Payroll\Models\Compensation;
use Modules\Payroll\Models\PayrollRun;

/**
 * A salary for every employee and one processed run for last month.
 * Amounts are minor units; percentages are basis points.
 */
class PayrollDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PayrollSetupSeeder::class);
        $this->compensations();
        $this->lastMonthRun();
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
