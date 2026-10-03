<?php

namespace Modules\Attendance\Widgets;

use Modules\AccessControl\Services\Access;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;
use Modules\Shared\Contracts\DashboardWidget;

/**
 * Checked-in count for the viewer's attendance.view scope (team for managers, company for HR).
 */
final class TeamAttendanceTodayWidget implements DashboardWidget
{
    public function __construct(private readonly Access $access) {}

    public function key(): string
    {
        return 'attendance.team_today';
    }

    public function title(): string
    {
        return __('Checked in today');
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
        return 31;
    }

    public function visible(User $user): bool
    {
        $scope = $this->access->scope($user, 'attendance.view');

        return $scope !== null && $scope->value !== 'own';
    }

    public function data(User $user): array
    {
        $ids = $this->access->visibleEmployeeIds($user, 'attendance.view');
        $today = now()->toDateString();

        $checkedIn = AttendanceRecord::query()->withinIds($ids)->whereDate('date', $today)->whereNotNull('check_in_at')->count();
        $total = Employee::query()->employed()->withinIds($ids)->count();

        return [
            'value' => "{$checkedIn} / {$total}",
            'caption' => __('employees checked in'),
            'href' => route('attendance.records.index'),
        ];
    }
}
