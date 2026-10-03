<?php

namespace Modules\Payroll\Data;

use Modules\Payroll\Enums\ComponentType;
use Modules\Shared\ValueObjects\Money;

final readonly class PayslipLineResult
{
    public function __construct(
        public string $code,
        public string $name,
        public ComponentType $type,
        public Money $amount,
    ) {}
}
