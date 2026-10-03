<?php

namespace Modules\Leave\Actions;

use Carbon\CarbonInterface;
use Modules\Employees\Models\Employee;
use Modules\Leave\Enums\AccrualMethod;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Services\LeaveBalanceAllocator;

/**
 * Adds quota/12 to every monthly-accrual balance, once per month.
 */
final class AccrueMonthlyBalances
{
    public function __construct(private readonly LeaveBalanceAllocator $allocator) {}

    /**
     * @return int number of balances credited
     */
    public function handle(CarbonInterface $month): int
    {
        $credited = 0;
        $types = LeaveType::query()->active()->where('accrual_method', AccrualMethod::Monthly->value)->get();

        Employee::query()
            ->employed()
            ->orderBy('id')
            ->each(function (Employee $employee) use ($types, $month, &$credited): void {
                foreach ($types as $type) {
                    $credited += $this->allocator->accrue($employee, $type, $month) ? 1 : 0;
                }
            });

        return $credited;
    }
}
