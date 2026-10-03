<?php

namespace Modules\Leave\Policies;

use Modules\AccessControl\Services\Access;
use Modules\Identity\Models\User;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Services\ApprovalFlow;

/**
 * Viewing a request (and its attachment): the owner, anyone whose leave.view / leave.approve scope covers
 * the employee, or an approver who can act on it.
 */
final class LeaveRequestPolicy
{
    public function __construct(
        private readonly Access $access,
        private readonly ApprovalFlow $flow,
    ) {}

    public function view(User $user, LeaveRequest $request): bool
    {
        if ($request->employee->user_id === $user->id) {
            return true;
        }

        return $this->access->can($user, 'leave.view', $request->employee_id)
            || $this->access->can($user, 'leave.approve', $request->employee_id)
            || $this->flow->canAct($user, $request);
    }
}
