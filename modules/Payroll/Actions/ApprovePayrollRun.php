<?php

namespace Modules\Payroll\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\User;
use Modules\Payroll\Enums\PayrollRunStatus;
use Modules\Payroll\Events\PayrollRunChanged;
use Modules\Payroll\Models\PayrollRun;
use Modules\Shared\Exceptions\DomainException;
use Modules\Shared\Exceptions\InvalidStateTransition;

final class ApprovePayrollRun
{
    public function handle(PayrollRun $run, User $by): PayrollRun
    {
        DB::transaction(function () use ($run, $by): void {
            $run->lockAndRefresh();

            if (! $run->status->canTransitionTo(PayrollRunStatus::Approved)) {
                throw InvalidStateTransition::from('payroll run', $run->status->value, PayrollRunStatus::Approved->value);
            }

            if ($run->created_by === $by->id) {
                throw new DomainException(__('The approver must be a different person than the one who created the run.'), 'approver');
            }

            $run->update(['status' => PayrollRunStatus::Approved, 'approved_by' => $by->id]);
        });

        PayrollRunChanged::dispatch($run, 'approved', $by->id);

        return $run;
    }
}
