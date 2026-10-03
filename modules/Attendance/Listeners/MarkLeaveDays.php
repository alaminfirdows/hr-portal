<?php

namespace Modules\Attendance\Listeners;

use Carbon\CarbonImmutable;
use Modules\Attendance\Enums\AttendanceSource;
use Modules\Attendance\Enums\AttendanceStatus;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Leave\Events\LeaveRequestApproved;
use Modules\Shared\Contracts\WorkCalendar;

/**
 * Leave approved: mark working days up to today as on_leave. Days already finalized as absent by the
 * system are corrected; real check-ins and manual edits are never overwritten. Future days are left
 * to the nightly finalize, which reads the LeaveCalendar contract.
 */
final class MarkLeaveDays
{
    public function __construct(private readonly WorkCalendar $calendar) {}

    public function handle(LeaveRequestApproved $event): void
    {
        $request = $event->request;
        $today = now()->toImmutable()->startOfDay();
        $end = $request->end_date->min($today);

        for ($day = $request->start_date; $day->lte($end); $day = $day->addDay()) {
            if (! $this->calendar->isWorkingDay($day)) {
                continue;
            }

            $this->mark($request->employee_id, $day);
        }
    }

    private function mark(int $employeeId, CarbonImmutable $day): void
    {
        $record = AttendanceRecord::query()->where('employee_id', $employeeId)->whereDate('date', $day->toDateString())->first();

        if ($record === null) {
            AttendanceRecord::query()->create([
                'employee_id' => $employeeId,
                'date' => $day->toDateString(),
                'status' => AttendanceStatus::OnLeave,
                'source' => AttendanceSource::System,
            ]);

            return;
        }

        if ($record->isSystemRow() && $record->status === AttendanceStatus::Absent) {
            $record->update(['status' => AttendanceStatus::OnLeave]);
        }
    }
}
