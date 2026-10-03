<?php

namespace Modules\Attendance\Listeners;

use Modules\Attendance\Enums\AttendanceSource;
use Modules\Attendance\Enums\AttendanceStatus;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Leave\Events\LeaveRequestCancelled;

/**
 * Approved leave cancelled: drop system on_leave rows in its range. Days before today turn back into
 * absent (already finalized); today and later are left to finalize.
 */
final class ClearLeaveDays
{
    public function handle(LeaveRequestCancelled $event): void
    {
        if (! $event->wasApproved) {
            return;
        }

        $request = $event->request;
        $today = now()->toDateString();

        $rows = AttendanceRecord::query()
            ->where('employee_id', $request->employee_id)
            ->betweenDates($request->start_date->toDateString(), $request->end_date->toDateString())
            ->where('status', AttendanceStatus::OnLeave->value)
            ->where('source', AttendanceSource::System->value)
            ->whereNull('check_in_at')
            ->get();

        foreach ($rows as $row) {
            if ($row->date->toDateString() < $today) {
                $row->update(['status' => AttendanceStatus::Absent]);
            } else {
                $row->delete();
            }
        }
    }
}
