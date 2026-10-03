<?php

namespace Modules\Leave\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Leave\Database\Factories\LeaveTypeFactory;
use Modules\Leave\Enums\AccrualMethod;
use Modules\Leave\Enums\ApprovalStepRole;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $color
 * @property bool $is_paid
 * @property bool $requires_attachment
 * @property bool $allow_half_day
 * @property AccrualMethod $accrual_method
 * @property float|null $annual_quota null = unlimited
 * @property float $carry_forward_cap
 * @property bool $prorate_new_joiners
 * @property list<string> $approval_steps
 * @property int|null $max_consecutive_days
 * @property bool $is_active
 */
#[Fillable([
    'name', 'code', 'color', 'is_paid', 'requires_attachment', 'allow_half_day', 'accrual_method', 'annual_quota',
    'carry_forward_cap', 'prorate_new_joiners', 'approval_steps', 'max_consecutive_days', 'is_active',
])]
class LeaveType extends Model
{
    /** @use HasFactory<LeaveTypeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'requires_attachment' => 'boolean',
            'allow_half_day' => 'boolean',
            'accrual_method' => AccrualMethod::class,
            'annual_quota' => 'float',
            'carry_forward_cap' => 'float',
            'prorate_new_joiners' => 'boolean',
            'approval_steps' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function isUnlimited(): bool
    {
        return $this->annual_quota === null;
    }

    /**
     * @return list<ApprovalStepRole>
     */
    public function configuredSteps(): array
    {
        $steps = [];

        foreach ($this->approval_steps as $step) {
            $role = ApprovalStepRole::tryFrom($step);

            if ($role !== null) {
                $steps[] = $role;
            }
        }

        return $steps;
    }

    /**
     * @return HasMany<LeaveRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
