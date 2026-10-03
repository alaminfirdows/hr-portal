<?php

namespace Modules\Payroll\Data;

use Modules\Shared\ValueObjects\Money;

/**
 * Everything the calculator needs for one employee and period. Pure data: no I/O.
 */
final readonly class PayrollContext
{
    /**
     * @param  Money  $monthlyBasic  full-month basic salary
     * @param  int  $workingDays  company working days in the whole period
     * @param  int  $employedWorkingDays  working days inside the period the employee was employed (joined/exited mid period)
     * @param  list<ComponentSpec>  $components  in evaluation (sort) order
     * @param  list<AdjustmentSpec>  $adjustments
     * @param  list<TaxBracket>  $taxBrackets
     */
    public function __construct(
        public Money $monthlyBasic,
        public int $workingDays,
        public int $employedWorkingDays,
        public float $unpaidLeaveDays = 0.0,
        public int $absentDays = 0,
        public int $overtimeMinutes = 0,
        public float $yearsOfService = 0.0,
        public array $components = [],
        public array $adjustments = [],
        public array $taxBrackets = [],
    ) {}
}
