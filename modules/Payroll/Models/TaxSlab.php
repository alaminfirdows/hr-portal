<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Monthly taxable income band. `rate_bp` is basis points (10% = 1000).
 *
 * @property int $id
 * @property int $min_amount
 * @property int|null $max_amount
 * @property int $rate_bp
 */
#[Fillable(['min_amount', 'max_amount', 'rate_bp'])]
class TaxSlab extends Model
{
    protected function casts(): array
    {
        return ['min_amount' => 'integer', 'max_amount' => 'integer', 'rate_bp' => 'integer'];
    }
}
