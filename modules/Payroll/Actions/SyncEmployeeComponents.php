<?php

namespace Modules\Payroll\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Employees\Models\Employee;
use Modules\Payroll\Events\PayrollConfigChanged;
use Modules\Payroll\Models\EmployeePayComponent;

/**
 * Replaces the components assigned to an employee (and their amount overrides).
 */
final class SyncEmployeeComponents
{
    /**
     * @param  array<int, int|null>  $assignments  pay_component_id => override amount in minor units (null = none)
     */
    public function handle(Employee $employee, array $assignments, ?int $actorId = null): void
    {
        $before = EmployeePayComponent::query()->where('employee_id', $employee->id)->pluck('amount', 'pay_component_id')->all();

        DB::transaction(function () use ($employee, $assignments): void {
            EmployeePayComponent::query()->where('employee_id', $employee->id)->whereNotIn('pay_component_id', array_keys($assignments))->delete();

            foreach ($assignments as $componentId => $amount) {
                EmployeePayComponent::query()->updateOrCreate(
                    ['employee_id' => $employee->id, 'pay_component_id' => $componentId],
                    ['amount' => $amount],
                );
            }
        });

        if ($before !== $assignments) {
            PayrollConfigChanged::dispatch($employee, 'updated', "{$employee->full_name} components", ['components' => $before], ['components' => $assignments], $actorId);
        }
    }
}
