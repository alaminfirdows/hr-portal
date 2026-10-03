<?php

namespace Modules\Leave\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Modules\AccessControl\Services\Access;
use Modules\Identity\Models\User;
use Modules\Leave\Enums\LeaveStatus;
use Modules\Leave\Events\LeaveRequestCancelled;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Services\LeaveBalanceService;
use Modules\Shared\Exceptions\DomainException;

/**
 * Cancel a pending request (releases pending) or an approved one that has not started (restores used).
 */
final class CancelLeaveRequest
{
    public function __construct(
        private readonly LeaveBalanceService $balances,
        private readonly Access $access,
    ) {}

    public function handle(LeaveRequest $request, User $by): LeaveRequest
    {
        $wasApproved = DB::transaction(function () use ($request, $by): bool {
            $locked = LeaveRequest::query()->with(['employee', 'type'])->lockForUpdate()->findOrFail($request->id);

            $isOwner = $locked->employee->user_id === $by->id;

            if (! $isOwner && ! $this->access->can($by, 'leave.manage', $locked->employee_id)) {
                throw new AuthorizationException(__('You cannot cancel this request.'));
            }

            if (! in_array($locked->status, [LeaveStatus::Pending, LeaveStatus::Approved], true)) {
                throw new DomainException(__('Only pending or approved requests can be cancelled.'), 'request');
            }

            $approved = $locked->status === LeaveStatus::Approved;

            if ($approved && $locked->start_date->lt(now()->startOfDay())) {
                throw new DomainException(__('Leave that has already started cannot be cancelled.'), 'request');
            }

            $this->balances->move($locked, $approved ? 0 : -$locked->days, $approved ? -$locked->days : 0);

            $locked->status = LeaveStatus::Cancelled;
            $locked->decided_at = now()->toImmutable();
            $locked->save();

            $request->setRawAttributes($locked->getAttributes(), true);
            $request->setRelation('employee', $locked->employee);
            $request->setRelation('type', $locked->type);

            return $approved;
        });

        LeaveRequestCancelled::dispatch($request, $wasApproved, $by->id);

        return $request;
    }
}
