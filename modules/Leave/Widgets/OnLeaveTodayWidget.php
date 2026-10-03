<?php

namespace Modules\Leave\Widgets;

use Modules\AccessControl\Services\Access;
use Modules\Identity\Models\User;
use Modules\Leave\Enums\LeaveStatus;
use Modules\Leave\Models\LeaveRequest;
use Modules\Shared\Contracts\DashboardWidget;

final class OnLeaveTodayWidget implements DashboardWidget
{
    public function __construct(private readonly Access $access) {}

    public function key(): string
    {
        return 'leave.on_leave_today';
    }

    public function title(): string
    {
        return __('On leave today');
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
        return 22;
    }

    public function visible(User $user): bool
    {
        return $this->access->scope($user, 'leave.view') !== null;
    }

    public function data(User $user): array
    {
        $today = now()->toDateString();

        return [
            'items' => LeaveRequest::query()
                ->with(['employee', 'type'])
                ->withinIds($this->access->visibleEmployeeIds($user, 'leave.view'))
                ->where('status', LeaveStatus::Approved->value)
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->limit(8)
                ->get()
                ->map(fn (LeaveRequest $r) => [
                    'id' => $r->id,
                    'title' => $r->employee->full_name,
                    'subtitle' => $r->type->name,
                    'meta' => __('until :date', ['date' => $r->end_date->format('M j')]),
                    'href' => route('leave.requests.show', $r),
                ])->all(),
            'empty' => __('Nobody is on leave.'),
        ];
    }
}
