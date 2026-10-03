<?php

namespace Modules\Attendance\Actions;

use Carbon\CarbonImmutable;
use Modules\Attendance\Enums\AttendanceSource;
use Modules\Attendance\Enums\AttendanceStatus;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Employees\Models\Employee;
use Modules\Shared\Contracts\LeaveCalendar;
use Modules\Shared\Contracts\WorkCalendar;

/**
 * End-of-day pass: writes weekend, holiday, on-leave or absent rows for employees without a record.
 * Never touches an existing record, so it is safe to re-run.
 */
final class FinalizeAttendanceDay
{
    public function __construct(
        private readonly WorkCalendar $calendar,
        private readonly LeaveCalendar $leave,
    ) {}

    /**
     * @return int rows created
     */
    public function handle(CarbonImmutable $date): int
    {
        $day = $date->startOfDay();

        $status = match (true) {
            $this->calendar->isWeekend($day) => AttendanceStatus::Weekend,
            $this->calendar->isHoliday($day) => AttendanceStatus::Holiday,
            default => null,
        };

        $onLeave = $status === null ? array_flip($this->leave->employeesOnLeave($day)) : [];

        $existing = array_flip(AttendanceRecord::query()->whereDate('date', $day->toDateString())->pluck('employee_id')->all());

        $created = 0;

        Employee::query()
            ->employed()
            ->whereDate('joined_on', '<=', $day->toDateString())
            ->where(fn ($q) => $q->whereNull('exited_on')->orWhereDate('exited_on', '>=', $day->toDateString()))
            ->select('id')
            ->chunkById(200, function ($employees) use ($day, $status, $onLeave, $existing, &$created): void {
                foreach ($employees as $employee) {
                    if (isset($existing[$employee->id])) {
                        continue;
                    }

                    AttendanceRecord::query()->create([
                        'employee_id' => $employee->id,
                        'date' => $day->toDateString(),
                        'status' => $status ?? (isset($onLeave[$employee->id]) ? AttendanceStatus::OnLeave : AttendanceStatus::Absent),
                        'source' => AttendanceSource::System,
                    ]);
                    $created++;
                }
            });

        return $created;
    }
}
