<?php

namespace Modules\Leave\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Identity\Models\User;
use Modules\Leave\Enums\ApprovalDecision;
use Modules\Leave\Enums\ApprovalStepRole;

/**
 * @property int $id
 * @property int $leave_request_id
 * @property int $step
 * @property ApprovalStepRole $step_role
 * @property int|null $approver_id
 * @property ApprovalDecision $decision
 * @property string|null $comment
 * @property CarbonImmutable|null $created_at
 * @property-read User|null $approver
 */
#[Fillable(['leave_request_id', 'step', 'step_role', 'approver_id', 'decision', 'comment'])]
class LeaveApproval extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'step_role' => ApprovalStepRole::class,
            'decision' => ApprovalDecision::class,
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * @return BelongsTo<LeaveRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class, 'leave_request_id');
    }
}
