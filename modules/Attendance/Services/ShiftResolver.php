<?php

namespace Modules\Attendance\Services;

use Modules\Attendance\Models\EmployeeShift;
use Modules\Attendance\Models\Shift;

/**
 * Finds the shift an employee works: assigned shift, else default shift, else a built-in 09:00-18:00 shift.
 */
final class ShiftResolver
{
    public function forEmployee(int $employeeId): Shift
    {
        $assigned = EmployeeShift::query()->with('shift')->where('employee_id', $employeeId)->first();

        if ($assigned !== null && $assigned->shift->is_active) {
            return $assigned->shift;
        }

        return $this->default();
    }

    public function default(): Shift
    {
        return Shift::query()->active()->where('is_default', true)->first() ?? Shift::fallback();
    }
}
