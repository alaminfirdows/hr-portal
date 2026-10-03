<?php

namespace Modules\Employees\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Identity\Models\User;

/**
 * @property int $id
 * @property int $employee_id
 * @property string $field
 * @property string|null $old_value
 * @property string|null $new_value
 * @property string|null $reason
 * @property CarbonImmutable $effective_on
 * @property int|null $changed_by
 * @property CarbonImmutable|null $created_at
 * @property-read User|null $changer
 */
#[Fillable(['employee_id', 'field', 'old_value', 'new_value', 'reason', 'effective_on', 'changed_by', 'created_at'])]
class EmployeeHistory extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'effective_on' => 'immutable_date',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
