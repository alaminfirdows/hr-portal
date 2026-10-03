<?php

namespace Modules\Payroll\Data;

use Modules\Shared\ValueObjects\Money;

final readonly class PayslipResult
{
    /**
     * @param  Money  $basic  pro-rated basic
     * @param  Money  $net  never negative; see $netClamped
     * @param  list<PayslipLineResult>  $lines
     */
    public function __construct(
        public Money $basic,
        public Money $gross,
        public Money $totalDeductions,
        public Money $net,
        public bool $netClamped,
        public int $workingDays,
        public float $paidDays,
        public float $unpaidDays,
        public int $overtimeMinutes,
        public array $lines,
    ) {}
}
