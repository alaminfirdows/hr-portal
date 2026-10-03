<?php

namespace Modules\Payroll\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\User;
use Modules\Payroll\Enums\PayrollRunStatus;
use Modules\Payroll\Events\PayrollRunChanged;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Notifications\PayslipAvailable;
use Modules\Shared\Exceptions\InvalidStateTransition;

/**
 * Final step: publishes every payslip to its employee and notifies them.
 */
final class ProcessPayrollRun
{
    public function handle(PayrollRun $run, ?User $by = null): PayrollRun
    {
        DB::transaction(function () use ($run): void {
            $run->lockAndRefresh();

            if (! $run->status->canTransitionTo(PayrollRunStatus::Processed)) {
                throw InvalidStateTransition::from('payroll run', $run->status->value, PayrollRunStatus::Processed->value);
            }

            $now = now();
            Payslip::query()->where('payroll_run_id', $run->id)->update(['published_at' => $now]);
            $run->update(['status' => PayrollRunStatus::Processed, 'processed_at' => $now]);
        });

        PayrollRunChanged::dispatch($run, 'processed', $by?->id);

        Payslip::query()->where('payroll_run_id', $run->id)->with(['employee.user', 'run'])->each(function (Payslip $payslip): void {
            $payslip->employee->user?->notify(new PayslipAvailable($payslip));
        });

        return $run;
    }
}
