<?php

namespace Modules\Leave\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Leave\Enums\AccrualMethod;
use Modules\Leave\Models\LeaveType;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    protected $model = LeaveType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'code' => strtoupper(fake()->unique()->lexify('????')),
            'color' => 'blue',
            'is_paid' => true,
            'requires_attachment' => false,
            'allow_half_day' => true,
            'accrual_method' => AccrualMethod::Yearly,
            'annual_quota' => 18,
            'carry_forward_cap' => 0,
            'prorate_new_joiners' => true,
            'approval_steps' => ['manager', 'hr'],
            'max_consecutive_days' => null,
            'is_active' => true,
        ];
    }

    public function unlimited(): static
    {
        return $this->state(['annual_quota' => null, 'accrual_method' => AccrualMethod::None]);
    }
}
