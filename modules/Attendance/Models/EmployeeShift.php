<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Employees\Models\Employee;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $shift_id
 * @property-read Shift $shift
 * @property-read Employee $employee
 */
#[Fillable(['employee_id', 'shift_id'])]
class EmployeeShift extends Model
{
    /**
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
