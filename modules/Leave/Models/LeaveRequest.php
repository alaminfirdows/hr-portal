<?php

namespace Modules\Leave\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Employees\Models\Employee;
use Modules\Leave\Database\Factories\LeaveRequestFactory;
use Modules\Leave\Enums\ApprovalStepRole;
use Modules\Leave\Enums\DayPart;
use Modules\Leave\Enums\LeaveStatus;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $leave_type_id
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable $end_date
 * @property DayPart $day_part
 * @property float $days
 * @property string|null $reason
 * @property string|null $attachment_path
 * @property LeaveStatus $status
 * @property list<string> $steps Approval steps resolved at submission
 * @property int $current_step
 * @property CarbonImmutable|null $decided_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Employee $employee
 * @property-read LeaveType $type
 */
#[Fillable([
    'employee_id', 'leave_type_id', 'start_date', 'end_date', 'day_part', 'days', 'reason', 'attachment_path',
    'status', 'steps', 'current_step', 'decided_at',
])]
class LeaveRequest extends Model
{
    /** @use HasFactory<LeaveRequestFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'day_part' => DayPart::class,
            'days' => 'float',
            'status' => LeaveStatus::class,
            'steps' => 'array',
            'current_step' => 'integer',
            'decided_at' => 'immutable_datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @param  list<int>|null  $ids  null = no restriction
     */
    public function scopeWithinIds(Builder $query, ?array $ids): void
    {
        if ($ids !== null) {
            $query->whereIn('leave_requests.employee_id', $ids);
        }
    }

    /**
     * Requests that hold or consume balance (pending or approved).
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', [LeaveStatus::Pending->value, LeaveStatus::Approved->value]);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->whereDate('start_date', '<=', $to->toDateString())->whereDate('end_date', '>=', $from->toDateString());
    }

    public function currentRole(): ?ApprovalStepRole
    {
        $role = $this->steps[$this->current_step] ?? null;

        return $role === null ? null : ApprovalStepRole::tryFrom($role);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<LeaveType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    /**
     * @return HasMany<LeaveApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(LeaveApproval::class)->orderBy('id');
    }
}
