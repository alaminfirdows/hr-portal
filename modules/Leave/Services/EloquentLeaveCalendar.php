<?php

namespace Modules\Leave\Services;

use Carbon\CarbonInterface;
use Modules\Leave\Enums\DayPart;
use Modules\Leave\Enums\LeaveStatus;
use Modules\Leave\Models\LeaveRequest;
use Modules\Shared\Contracts\LeaveCalendar;
use Modules\Shared\Contracts\WorkCalendar;

/**
 * Approved leave lookups for other modules (attendance, payroll).
 */
final class EloquentLeaveCalendar implements LeaveCalendar
{
    public function __construct(private readonly WorkCalendar $calendar) {}

    public function isOnLeave(int $employeeId, CarbonInterface $date): bool
    {
        return LeaveRequest::query()
            ->where('employee_id', $employeeId)
            ->where('status', LeaveStatus::Approved->value)
            ->overlapping($date, $date)
            ->exists();
    }

    public function unpaidLeaveDays(int $employeeId, CarbonInterface $from, CarbonInterface $to): float
    {
        $total = 0.0;

        $requests = LeaveRequest::query()
            ->with('type:id,is_paid')
            ->where('employee_id', $employeeId)
            ->where('status', LeaveStatus::Approved->value)
            ->overlapping($from, $to)
            ->get();

        foreach ($requests as $request) {
            if ($request->type->is_paid) {
                continue;
            }

            $start = $request->start_date->max($from->toImmutable()->startOfDay());
            $end = $request->end_date->min($to->toImmutable()->startOfDay());
            $working = $this->calendar->workingDaysBetween($start, $end);

            $total += $request->day_part === DayPart::Full ? $working : 0.5 * $working;
        }

        return $total;
    }

    public function employeesOnLeave(CarbonInterface $date): array
    {
        return array_values(LeaveRequest::query()
            ->where('status', LeaveStatus::Approved->value)
            ->overlapping($date, $date)
            ->pluck('employee_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all());
    }
}
