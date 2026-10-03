<?php

namespace Modules\Payroll\Enums;

use Modules\Shared\Concerns\HasOptions;

enum CalculationMethod: string
{
    use HasOptions;

    case Fixed = 'fixed';
    case PercentOfBasic = 'percent_of_basic';
    case Formula = 'formula';
    case TaxSlab = 'tax_slab';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed amount',
            self::PercentOfBasic => 'Percent of basic',
            self::Formula => 'Formula',
            self::TaxSlab => 'Tax slabs',
        };
    }
}
