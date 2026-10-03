<?php

namespace Modules\Attendance\Services;

use Carbon\CarbonInterface;
use Modules\Attendance\Enums\AttendanceStatus;
use Modules\Attendance\Models\Shift;

/**
 * Pure rules: turns check-in/out times and a shift into status, lateness, worked and overtime minutes.
 *
 * - Late: check-in after shift start + grace. Late minutes count from the shift start.
 * - Half day: closed day worked less than the shift's half_day_minutes.
 * - Overtime: minutes worked beyond shift length once the excess passes overtime_after_minutes.
 * - Open day (no check-out yet): present, nothing worked or overtime counted.
 */
final class AttendanceEvaluator
{
    /**
     * @return array{status: AttendanceStatus, is_late: bool, late_minutes: int, worked_minutes: int, overtime_minutes: int}
     */
    public function evaluate(Shift $shift, CarbonInterface $checkIn, ?CarbonInterface $checkOut): array
    {
        $start = $checkIn->copy()->startOfDay()->addMinutes($shift->minutesOfDay($shift->start_time));
        $late = max(0, (int) $start->diffInMinutes($checkIn, false));
        $isLate = $late > $shift->grace_minutes;

        $worked = 0;
        $overtime = 0;
        $status = AttendanceStatus::Present;

        if ($checkOut !== null) {
            $worked = max(0, (int) $checkIn->diffInMinutes($checkOut, false));
            $excess = $worked - $shift->lengthMinutes();

            if ($excess > $shift->overtime_after_minutes) {
                $overtime = $excess;
            }

            if ($worked < $shift->half_day_minutes) {
                $status = AttendanceStatus::HalfDay;
            }
        }

        return [
            'status' => $status,
            'is_late' => $isLate,
            'late_minutes' => $isLate ? $late : 0,
            'worked_minutes' => $worked,
            'overtime_minutes' => $overtime,
        ];
    }
}
