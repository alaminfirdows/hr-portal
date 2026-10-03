<?php

namespace Modules\Leave\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\User;
use Modules\Leave\Enums\ApprovalDecision;
use Modules\Leave\Enums\LeaveStatus;
use Modules\Leave\Events\LeaveRequestRejected;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Services\ApprovalFlow;
use Modules\Leave\Services\LeaveBalanceService;
use Modules\Leave\Services\LeaveNotifier;
use Modules\Shared\Exceptions\DomainException;

/**
 * A rejection at any step ends the flow and releases the pending days.
 */
final class RejectLeaveRequest
{
    public function __construct(
        private readonly ApprovalFlow $flow,
        private readonly LeaveBalanceService $balances,
        private readonly LeaveNotifier $notifier,
    ) {}

    public function handle(LeaveRequest $request, User $by, ?string $comment = null): LeaveRequest
    {
        DB::transaction(function () use ($request, $by, $comment): void {
            $locked = LeaveRequest::query()->with(['employee', 'type'])->lockForUpdate()->findOrFail($request->id);

            if ($locked->status !== LeaveStatus::Pending) {
                throw new DomainException(__('This request has already been decided.'), 'request');
            }

            if (! $this->flow->canAct($by, $locked)) {
                throw new AuthorizationException(__('You cannot reject this request.'));
            }

            $locked->approvals()->create([
                'step' => $locked->current_step,
                'step_role' => $locked->currentRole()?->value,
                'approver_id' => $by->id,
                'decision' => ApprovalDecision::Rejected,
                'comment' => $comment,
            ]);

            $locked->status = LeaveStatus::Rejected;
            $locked->decided_at = now()->toImmutable();
            $locked->save();

            $this->balances->move($locked, -$locked->days, 0);

            $request->setRawAttributes($locked->getAttributes(), true);
            $request->setRelation('employee', $locked->employee);
            $request->setRelation('type', $locked->type);
        });

        LeaveRequestRejected::dispatch($request, $by->id, $comment);
        $this->notifier->decided($request, ApprovalDecision::Rejected, $comment);

        return $request;
    }
}
