<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Employees\Models\Employee;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\Payslip;

/**
 * @extends Factory<Payslip>
 */
class PayslipFactory extends Factory
{
    protected $model = Payslip::class;

    public function definition(): array
    {
        return [
            'payroll_run_id' => PayrollRun::factory(),
            'employee_id' => Employee::factory(),
            'basic' => 300000,
            'gross' => 300000,
            'total_deductions' => 0,
            'net' => 300000,
            'net_clamped' => false,
            'working_days' => 22,
            'paid_days' => 22,
            'unpaid_days' => 0,
            'overtime_minutes' => 0,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(['published_at' => now()]);
    }
}
