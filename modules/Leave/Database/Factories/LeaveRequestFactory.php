<?php

namespace Modules\Leave\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Employees\Models\Employee;
use Modules\Leave\Enums\DayPart;
use Modules\Leave\Enums\LeaveStatus;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\LeaveType;

/**
 * Raw rows for tests and seeders; does not touch balances.
 *
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(7, 60))->toDateString();

        return [
            'employee_id' => Employee::factory(),
            'leave_type_id' => LeaveType::factory(),
            'start_date' => $start,
            'end_date' => $start,
            'day_part' => DayPart::Full,
            'days' => 1,
            'reason' => fake()->sentence(),
            'status' => LeaveStatus::Pending,
            'steps' => ['manager', 'hr'],
            'current_step' => 0,
        ];
    }

    public function status(LeaveStatus $status): static
    {
        return $this->state(['status' => $status, 'decided_at' => $status === LeaveStatus::Pending ? null : now()]);
    }
}
