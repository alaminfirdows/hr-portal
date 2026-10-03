<?php

namespace Modules\Payroll\Data;

use Modules\Payroll\Enums\CalculationMethod;
use Modules\Payroll\Enums\ComponentType;

/**
 * A pay component as seen by the calculator for one employee. List order = evaluation order.
 */
final readonly class ComponentSpec
{
    /**
     * @param  int|null  $value  minor units (fixed) or basis points (percent_of_basic)
     * @param  int|null  $override  per-employee amount in minor units replacing the computed amount
     */
    public function __construct(
        public string $code,
        public string $name,
        public ComponentType $type,
        public CalculationMethod $method,
        public ?int $value = null,
        public ?string $formula = null,
        public bool $taxable = false,
        public ?int $override = null,
    ) {}
}
