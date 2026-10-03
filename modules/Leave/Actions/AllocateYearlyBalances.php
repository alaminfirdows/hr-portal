<?php

namespace Modules\Leave\Actions;

use Modules\Employees\Models\Employee;
use Modules\Leave\Enums\AccrualMethod;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Services\LeaveBalanceAllocator;

/**
 * Creates the year's balance rows: full (or pro-rated) quota for yearly types, carry forward for yearly and
 * monthly types. Existing rows are left alone, so the command is safe to re-run.
 */
final class AllocateYearlyBalances
{
    public function __construct(private readonly LeaveBalanceAllocator $allocator) {}

    /**
     * @return int number of balance rows created
     */
    public function handle(int $year): int
    {
        $created = 0;
        $types = LeaveType::query()->active()->where('accrual_method', '!=', AccrualMethod::None->value)->get();

        Employee::query()
            ->employed()
            ->whereYear('joined_on', '<=', $year)
            ->orderBy('id')
            ->each(function (Employee $employee) use ($types, $year, &$created): void {
                foreach ($types as $type) {
                    $balance = $this->allocator->allocate($employee, $type, $year);
                    $created += $balance->wasRecentlyCreated ? 1 : 0;
                }
            });

        return $created;
    }
}
