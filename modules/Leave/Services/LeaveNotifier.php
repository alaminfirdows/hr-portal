<?php

namespace Modules\Leave\Services;

use Modules\Identity\Models\User;
use Modules\Leave\Enums\ApprovalDecision;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Notifications\ApprovalNeededNotification;
use Modules\Leave\Notifications\LeaveDecisionNotification;

final class LeaveNotifier
{
    public function __construct(private readonly ApprovalFlow $flow) {}

    /**
     * Tell whoever can act on the current step.
     */
    public function approvalNeeded(LeaveRequest $request): void
    {
        $request->loadMissing(['employee', 'type']);

        User::query()
            ->whereIn('id', $this->flow->approverUserIds($request))
            ->get()
            ->each(fn (User $user) => $user->notify(new ApprovalNeededNotification($request)));
    }

    public function decided(LeaveRequest $request, ApprovalDecision $decision, ?string $comment = null): void
    {
        $request->loadMissing(['employee.user', 'type']);

        $request->employee->user?->notify(new LeaveDecisionNotification($request, $decision, $comment));
    }
}
