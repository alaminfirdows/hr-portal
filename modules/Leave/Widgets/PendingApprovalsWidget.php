<?php

namespace Modules\Leave\Widgets;

use Modules\AccessControl\Services\Access;
use Modules\Identity\Models\User;
use Modules\Leave\Enums\LeaveStatus;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Services\ApprovalFlow;
use Modules\Shared\Contracts\DashboardWidget;

final class PendingApprovalsWidget implements DashboardWidget
{
    public function __construct(
        private readonly Access $access,
        private readonly ApprovalFlow $flow,
    ) {}

    public function key(): string
    {
        return 'leave.pending_approvals';
    }

    public function title(): string
    {
        return __('Leave awaiting you');
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
        return 21;
    }

    public function visible(User $user): bool
    {
        return $this->access->scope($user, 'leave.approve') !== null;
    }

    public function data(User $user): array
    {
        $count = LeaveRequest::query()
            ->with('employee')
            ->withinIds($this->access->visibleEmployeeIds($user, 'leave.approve'))
            ->where('status', LeaveStatus::Pending->value)
            ->get()
            ->filter(fn (LeaveRequest $r) => $this->flow->canAct($user, $r))
            ->count();

        return [
            'value' => $count,
            'caption' => __('pending requests'),
            'href' => route('leave.approvals.index'),
        ];
    }
}
