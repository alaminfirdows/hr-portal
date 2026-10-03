<?php

namespace Modules\Attendance\Widgets;

use Modules\AccessControl\Services\Access;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Identity\Models\User;
use Modules\Shared\Contracts\DashboardWidget;

final class LateTodayWidget implements DashboardWidget
{
    public function __construct(private readonly Access $access) {}

    public function key(): string
    {
        return 'attendance.late_today';
    }

    public function title(): string
    {
        return __('Late today');
    }

    public function type(): string
    {
        return 'list';
    }

    public function size(): int
    {
        return 2;
    }

    public function order(): int
    {
        return 32;
    }

    public function visible(User $user): bool
    {
        $scope = $this->access->scope($user, 'attendance.view');

        return $scope !== null && $scope->value !== 'own';
    }

    public function data(User $user): array
    {
        $items = AttendanceRecord::query()
            ->with('employee')
            ->withinIds($this->access->visibleEmployeeIds($user, 'attendance.view'))
            ->whereDate('date', now()->toDateString())
            ->where('is_late', true)
            ->orderByDesc('late_minutes')
            ->limit(8)
            ->get()
            ->map(fn (AttendanceRecord $r) => [
                'id' => $r->id,
                'title' => $r->employee->full_name,
                'subtitle' => __('In at :time', ['time' => $r->check_in_at?->format('H:i')]),
                'meta' => __(':n min late', ['n' => $r->late_minutes]),
                'href' => route('attendance.records.index'),
            ])->all();

        return ['items' => $items, 'empty' => __('Nobody is late.')];
    }
}
