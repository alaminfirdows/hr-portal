<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Identity\Models\User;
use Modules\Payroll\Enums\PayrollRunStatus;
use Modules\Payroll\Models\PayrollRun;

/**
 * @extends Factory<PayrollRun>
 */
class PayrollRunFactory extends Factory
{
    protected $model = PayrollRun::class;

    public function definition(): array
    {
        return [
            'period' => now()->subYears(20)->addMonths(fake()->unique()->numberBetween(0, 200))->format('Y-m'),
            'status' => PayrollRunStatus::Draft,
            'created_by' => User::factory(),
        ];
    }

    public function status(PayrollRunStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
