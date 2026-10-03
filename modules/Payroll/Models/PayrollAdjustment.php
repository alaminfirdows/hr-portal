<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Employees\Models\Employee;
use Modules\Payroll\Enums\ComponentType;

/**
 * One-off bonus or deduction for a single period.
 *
 * @property int $id
 * @property int $employee_id
 * @property string $period
 * @property ComponentType $type
 * @property string $name
 * @property int $amount
 * @property string|null $note
 * @property int|null $payroll_run_id
 * @property-read Employee $employee
 */
#[Fillable(['employee_id', 'period', 'type', 'name', 'amount', 'note', 'payroll_run_id'])]
class PayrollAdjustment extends Model
{
    protected function casts(): array
    {
        return ['type' => ComponentType::class, 'amount' => 'integer'];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
