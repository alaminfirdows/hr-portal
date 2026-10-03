<?php

namespace Modules\Payroll\Services;

use Modules\Payroll\Data\TaxBracket;
use Modules\Shared\ValueObjects\Money;

/**
 * Progressive tax: each slab taxes only the part of income that falls inside it.
 */
final class TaxCalculator
{
    /**
     * @param  list<TaxBracket>  $brackets
     */
    public function calculate(Money $taxable, array $brackets): Money
    {
        $tax = 0;

        foreach ($brackets as $bracket) {
            $upper = $bracket->max ?? PHP_INT_MAX;
            $portion = min($taxable->minor, $upper) - $bracket->min;

            if ($portion > 0) {
                $tax += (int) round($portion * $bracket->rateBp / 10000, 0, PHP_ROUND_HALF_UP);
            }
        }

        return Money::of($tax);
    }
}
