<?php

namespace Modules\Leave\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\User;
use Modules\Leave\Enums\ApprovalDecision;
use Modules\Leave\Enums\LeaveStatus;
use Modules\Leave\Events\LeaveRequestApproved;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Services\ApprovalFlow;
use Modules\Leave\Services\LeaveBalanceService;
use Modules\Leave\Services\LeaveNotifier;
use Modules\Shared\Exceptions\DomainException;

/**
 * Records the current step's approval; the last step approves the request and moves pending → used.
 */
final class ApproveLeaveRequest
{
    public function __construct(
        private readonly ApprovalFlow $flow,
        private readonly LeaveBalanceService $balances,
        private readonly LeaveNotifier $notifier,
    ) {}

    public function handle(LeaveRequest $request, User $by, ?string $comment = null): LeaveRequest
    {
        $completed = DB::transaction(function () use ($request, $by, $comment): bool {
            $locked = LeaveRequest::query()->with(['employee', 'type'])->lockForUpdate()->findOrFail($request->id);

            if ($locked->status !== LeaveStatus::Pending) {
                throw new DomainException(__('This request has already been decided.'), 'request');
            }

            if (! $this->flow->canAct($by, $locked)) {
                throw new AuthorizationException(__('You cannot approve this request.'));
            }

            $role = $locked->currentRole();

            $locked->approvals()->create([
                'step' => $locked->current_step,
                'step_role' => $role?->value,
                'approver_id' => $by->id,
                'decision' => ApprovalDecision::Approved,
                'comment' => $comment,
            ]);

            $request->setRawAttributes($locked->getAttributes(), true);
            $request->setRelation('employee', $locked->employee);
            $request->setRelation('type', $locked->type);

            if ($locked->current_step + 1 < count($locked->steps)) {
                $locked->current_step++;
                $locked->save();
                $request->setRawAttributes($locked->getAttributes(), true);

                return false;
            }

            $this->finalize($request);

            return true;
        });

        if ($completed) {
            LeaveRequestApproved::dispatch($request, $by->id);
            $this->notifier->decided($request, ApprovalDecision::Approved, $comment);
        } else {
            $this->notifier->approvalNeeded($request);
        }

        return $request;
    }

    /**
     * Approve without an approver (type configured with no steps).
     */
    public function complete(LeaveRequest $request, ?User $by): LeaveRequest
    {
        DB::transaction(fn () => $this->finalize($request));

        LeaveRequestApproved::dispatch($request, $by?->id);
        $this->notifier->decided($request, ApprovalDecision::Approved);

        return $request;
    }

    private function finalize(LeaveRequest $request): void
    {
        $request->status = LeaveStatus::Approved;
        $request->decided_at = now()->toImmutable();
        $request->save();

        $this->balances->move($request, -$request->days, $request->days);
    }
}
