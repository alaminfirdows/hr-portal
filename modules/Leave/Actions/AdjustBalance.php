<?php

namespace Modules\Leave\Actions;

use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;
use Modules\Leave\Events\LeaveBalanceAdjusted;
use Modules\Leave\Models\LeaveBalance;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Services\LeaveBalanceService;
use Modules\Shared\Exceptions\DomainException;

/**
 * Manual correction of a balance (positive or negative days), audited.
 */
final class AdjustBalance
{
    public function __construct(private readonly LeaveBalanceService $balances) {}

    public function handle(Employee $employee, LeaveType $type, int $year, float $delta, string $reason, ?User $by = null): LeaveBalance
    {
        if ($delta === 0.0) {
            throw new DomainException(__('The adjustment cannot be zero.'), 'delta');
        }

        $balance = $this->balances->balance($employee, $type, $year);
        $before = $balance->adjustment;

        $balance->adjustment = round($before + $delta, 2);
        $balance->save();
        $balance->setRelation('type', $type);
        $balance->setRelation('employee', $employee);

        LeaveBalanceAdjusted::dispatch($balance, $before, $reason, $by?->id);

        return $balance;
    }
}
