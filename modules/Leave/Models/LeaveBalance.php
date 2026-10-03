<?php

namespace Modules\Leave\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Employees\Models\Employee;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $leave_type_id
 * @property int $year
 * @property float $entitled
 * @property float $carried_forward
 * @property float $adjustment
 * @property float $used
 * @property float $pending
 * @property string|null $last_accrued_month
 * @property-read LeaveType $type
 * @property-read Employee $employee
 */
#[Fillable(['employee_id', 'leave_type_id', 'year', 'entitled', 'carried_forward', 'adjustment', 'used', 'pending', 'last_accrued_month'])]
class LeaveBalance extends Model
{
    protected function casts(): array
    {
        return [
            'entitled' => 'float',
            'carried_forward' => 'float',
            'adjustment' => 'float',
            'used' => 'float',
            'pending' => 'float',
        ];
    }

    /**
     * entitled + carried forward + adjustment - used - pending
     */
    public function available(): float
    {
        return round($this->entitled + $this->carried_forward + $this->adjustment - $this->used - $this->pending, 2);
    }

    /**
     * @return BelongsTo<LeaveType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
