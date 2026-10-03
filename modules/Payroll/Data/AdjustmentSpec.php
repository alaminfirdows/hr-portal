<?php

namespace Modules\Payroll\Data;

use Modules\Payroll\Enums\ComponentType;
use Modules\Shared\ValueObjects\Money;

final readonly class AdjustmentSpec
{
    public function __construct(
        public string $name,
        public ComponentType $type,
        public Money $amount,
    ) {}
}
