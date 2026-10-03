<?php

namespace Modules\Payroll\Data;

/**
 * Monthly taxable income band, [min, max). Null max = open ended. Rate in basis points.
 */
final readonly class TaxBracket
{
    public function __construct(
        public int $min,
        public ?int $max,
        public int $rateBp,
    ) {}
}
