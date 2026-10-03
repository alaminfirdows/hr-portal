<?php

namespace Modules\Attendance\Widgets;

use Modules\Attendance\Models\AttendanceRecord;
use Modules\Identity\Models\User;
use Modules\Shared\Contracts\DashboardWidget;
use Modules\Shared\Contracts\OrgHierarchy;

final class MyAttendanceTodayWidget implements DashboardWidget
{
    public function __construct(private readonly OrgHierarchy $org) {}

    public function key(): string
    {
        return 'attendance.my_today';
    }

    public function title(): string
    {
        return __('My attendance today');
    }

    public function type(): string
    {
        return 'stat';
    }

    public function size(): int
    {
        return 1;
    }

    public function order(): int
    {
        return 30;
    }

    public function visible(User $user): bool
    {
        return $this->org->employeeIdForUser($user->id) !== null && $user->can('attendance.check_in');
    }

    public function data(User $user): array
    {
        $employeeId = $this->org->employeeIdForUser($user->id);
        $record = $employeeId === null ? null : AttendanceRecord::query()
            ->where('employee_id', $employeeId)
            ->whereDate('date', now()->toDateString())
            ->first();

        [$value, $caption] = match (true) {
            $record?->check_out_at !== null => [__('Checked out'), __('Out at :time', ['time' => $record->check_out_at->format('H:i')])],
            $record?->check_in_at !== null => [__('Checked in'), __('In at :time', ['time' => $record->check_in_at->format('H:i')])],
            default => [__('Not checked in'), __('Open attendance to check in')],
        };

        return ['value' => $value, 'caption' => $caption, 'href' => route('attendance.my')];
    }
}
