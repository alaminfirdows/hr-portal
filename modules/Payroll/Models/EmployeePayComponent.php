<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $pay_component_id
 * @property int|null $amount Override in minor units; replaces the computed amount.
 * @property-read PayComponent $component
 */
#[Fillable(['employee_id', 'pay_component_id', 'amount'])]
class EmployeePayComponent extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    /**
     * @return BelongsTo<PayComponent, $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(PayComponent::class, 'pay_component_id');
    }
}
