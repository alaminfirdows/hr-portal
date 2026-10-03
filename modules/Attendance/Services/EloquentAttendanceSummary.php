<?php

namespace Modules\Attendance\Services;

use Carbon\CarbonInterface;
use Modules\Attendance\Enums\AttendanceStatus;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Shared\Contracts\AttendanceSummary;

/**
 * Attendance totals for payroll. Half days are in none of the day counts (contract has whole days only).
 */
final class EloquentAttendanceSummary implements AttendanceSummary
{
    public function forPeriod(int $employeeId, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = AttendanceRecord::query()
            ->where('employee_id', $employeeId)
            ->betweenDates($from->toDateString(), $to->toDateString())
            ->get(['status', 'is_late', 'overtime_minutes']);

        return [
            'present_days' => $rows->where('status', AttendanceStatus::Present)->count(),
            'absent_days' => $rows->where('status', AttendanceStatus::Absent)->count(),
            'late_days' => $rows->where('is_late', true)->count(),
            'overtime_minutes' => (int) $rows->sum('overtime_minutes'),
        ];
    }
}
