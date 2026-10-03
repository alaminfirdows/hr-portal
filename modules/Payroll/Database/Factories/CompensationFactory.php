<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Employees\Models\Employee;
use Modules\Payroll\Models\Compensation;

/**
 * @extends Factory<Compensation>
 */
class CompensationFactory extends Factory
{
    protected $model = Compensation::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'basic_salary' => 300000,
            'effective_from' => now()->subYear()->toDateString(),
            'effective_to' => null,
            'reason' => null,
        ];
    }
}
