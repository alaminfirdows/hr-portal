<?php

namespace Modules\Attendance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Attendance\Enums\AttendanceSource;
use Modules\Attendance\Enums\AttendanceStatus;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Employees\Models\Employee;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    protected $model = AttendanceRecord::class;

    public function definition(): array
    {
        $date = now()->subDays(fake()->numberBetween(1, 20))->toDateString();

        return [
            'employee_id' => Employee::factory(),
            'date' => $date,
            'check_in_at' => "{$date} 09:00:00",
            'check_out_at' => "{$date} 18:00:00",
            'status' => AttendanceStatus::Present,
            'is_late' => false,
            'late_minutes' => 0,
            'worked_minutes' => 540,
            'overtime_minutes' => 0,
            'source' => AttendanceSource::Web,
        ];
    }

    public function system(AttendanceStatus $status): static
    {
        return $this->state([
            'status' => $status,
            'source' => AttendanceSource::System,
            'check_in_at' => null,
            'check_out_at' => null,
            'worked_minutes' => 0,
        ]);
    }
}
