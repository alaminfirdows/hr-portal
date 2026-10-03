<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Payroll\Database\Factories\PayComponentFactory;
use Modules\Payroll\Enums\CalculationMethod;
use Modules\Payroll\Enums\ComponentType;

/**
 * `value` is minor units for fixed components and basis points for percent_of_basic.
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property ComponentType $type
 * @property CalculationMethod $calculation
 * @property int|null $value
 * @property string|null $formula
 * @property bool $is_taxable
 * @property bool $applies_to_all
 * @property bool $is_active
 * @property int $sort
 */
#[Fillable(['name', 'code', 'type', 'calculation', 'value', 'formula', 'is_taxable', 'applies_to_all', 'is_active', 'sort'])]
class PayComponent extends Model
{
    /** @use HasFactory<PayComponentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => ComponentType::class,
            'calculation' => CalculationMethod::class,
            'value' => 'integer',
            'is_taxable' => 'boolean',
            'applies_to_all' => 'boolean',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @return HasMany<EmployeePayComponent, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeePayComponent::class);
    }
}
