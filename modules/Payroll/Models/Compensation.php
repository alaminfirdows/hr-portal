<?php

namespace Modules\Payroll\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Employees\Models\Employee;
use Modules\Payroll\Database\Factories\CompensationFactory;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $basic_salary Minor units per month.
 * @property CarbonImmutable $effective_from
 * @property CarbonImmutable|null $effective_to
 * @property string|null $reason
 * @property int|null $created_by
 */
#[Fillable(['employee_id', 'basic_salary', 'effective_from', 'effective_to', 'reason', 'created_by'])]
class Compensation extends Model
{
    /** @use HasFactory<CompensationFactory> */
    use HasFactory;

    protected $table = 'compensations';

    protected function casts(): array
    {
        return [
            'basic_salary' => 'integer',
            'effective_from' => 'immutable_date',
            'effective_to' => 'immutable_date',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
