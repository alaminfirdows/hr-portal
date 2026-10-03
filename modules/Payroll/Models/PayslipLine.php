<?php

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Modules\Payroll\Enums\ComponentType;

/**
 * @property int $id
 * @property int $payslip_id
 * @property string $code
 * @property string $name
 * @property ComponentType $type
 * @property int $amount
 * @property int $sort
 */
#[Fillable(['payslip_id', 'code', 'name', 'type', 'amount', 'sort'])]
class PayslipLine extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['type' => ComponentType::class, 'amount' => 'integer', 'sort' => 'integer'];
    }
}
