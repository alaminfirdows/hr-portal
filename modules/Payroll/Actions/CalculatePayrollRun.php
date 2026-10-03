<?php

namespace Modules\Payroll\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Employees\Enums\EmployeeStatus;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;
use Modules\Payroll\Enums\PayrollRunStatus;
use Modules\Payroll\Events\PayrollRunChanged;
use Modules\Payroll\Models\PayrollAdjustment;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Models\PayslipLine;
use Modules\Payroll\Services\PayrollCalculator;
use Modules\Payroll\Services\PayrollContextBuilder;
use Modules\Shared\Exceptions\DomainException;
use Modules\Shared\Exceptions\InvalidStateTransition;

/**
 * Computes (or recomputes) every payslip of a run. All or nothing: any failure rolls the whole
 * calculation back and leaves the run in its previous state with its previous payslips.
 */
final class CalculatePayrollRun
{
    public function __construct(
        private readonly PayrollContextBuilder $contexts,
        private readonly PayrollCalculator $calculator,
    ) {}

    public function handle(PayrollRun $run, ?User $by = null): PayrollRun
    {
        DB::transaction(function () use ($run): void {
            $run->refresh();

            if (! $run->status->canTransitionTo(PayrollRunStatus::Calculated)) {
                throw InvalidStateTransition::from('payroll run', $run->status->value, PayrollRunStatus::Calculated->value);
            }

            $this->clear($run);

            $totals = ['count' => 0, 'gross' => 0, 'deductions' => 0, 'net' => 0];

            foreach ($this->eligibleEmployees($run) as $employee) {
                $context = $this->contexts->build($employee, $run);

                if ($context === null) {
                    continue;
                }

                try {
                    $result = $this->calculator->calculate($context);
                } catch (DomainException $e) {
                    throw new DomainException("{$employee->employee_code} {$employee->full_name}: {$e->getMessage()}", $e->field);
                }

                $payslip = Payslip::query()->create([
                    'payroll_run_id' => $run->id,
                    'employee_id' => $employee->id,
                    'basic' => $result->basic->minor,
                    'gross' => $result->gross->minor,
                    'total_deductions' => $result->totalDeductions->minor,
                    'net' => $result->net->minor,
                    'net_clamped' => $result->netClamped,
                    'working_days' => $result->workingDays,
                    'paid_days' => $result->paidDays,
                    'unpaid_days' => $result->unpaidDays,
                    'overtime_minutes' => $result->overtimeMinutes,
                ]);

                PayslipLine::query()->insert(array_map(fn ($line, int $i) => [
                    'payslip_id' => $payslip->id,
                    'code' => $line->code,
                    'name' => $line->name,
                    'type' => $line->type->value,
                    'amount' => $line->amount->minor,
                    'sort' => $i,
                ], $result->lines, array_keys($result->lines)));

                PayrollAdjustment::query()->where('employee_id', $employee->id)->where('period', $run->period)
                    ->update(['payroll_run_id' => $run->id]);

                $totals['count']++;
                $totals['gross'] += $result->gross->minor;
                $totals['deductions'] += $result->totalDeductions->minor;
                $totals['net'] += $result->net->minor;
            }

            $run->update([
                'status' => PayrollRunStatus::Calculated,
                'employee_count' => $totals['count'],
                'total_gross' => $totals['gross'],
                'total_deductions' => $totals['deductions'],
                'total_net' => $totals['net'],
                'reviewed_by' => null,
                'calculated_at' => now(),
            ]);
        });

        PayrollRunChanged::dispatch($run, 'calculated', $by?->id);

        return $run;
    }

    /**
     * Drops payslips from an earlier calculation and releases their adjustments.
     */
    private function clear(PayrollRun $run): void
    {
        $ids = Payslip::query()->where('payroll_run_id', $run->id)->pluck('id');

        PayslipLine::query()->whereIn('payslip_id', $ids)->delete();
        Payslip::query()->whereIn('id', $ids)->delete();
        PayrollAdjustment::query()->where('payroll_run_id', $run->id)->update(['payroll_run_id' => null]);
    }

    /**
     * @return iterable<Employee>
     */
    private function eligibleEmployees(PayrollRun $run): iterable
    {
        return Employee::query()
            ->whereNot('status', EmployeeStatus::Invited->value)
            ->whereDate('joined_on', '<=', $run->periodEnd())
            ->where(fn ($q) => $q->whereNull('exited_on')->orWhereDate('exited_on', '>=', $run->periodStart()))
            ->orderBy('employee_code')
            ->cursor();
    }
}
