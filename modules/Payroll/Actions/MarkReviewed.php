<?php

namespace Modules\Payroll\Actions;

use Modules\Identity\Models\User;
use Modules\Payroll\Enums\PayrollRunStatus;
use Modules\Payroll\Events\PayrollRunChanged;
use Modules\Payroll\Models\PayrollRun;
use Modules\Shared\Exceptions\InvalidStateTransition;

final class MarkReviewed
{
    public function handle(PayrollRun $run, User $by): PayrollRun
    {
        if (! $run->status->canTransitionTo(PayrollRunStatus::Reviewed)) {
            throw InvalidStateTransition::from('payroll run', $run->status->value, PayrollRunStatus::Reviewed->value);
        }

        $run->update(['status' => PayrollRunStatus::Reviewed, 'reviewed_by' => $by->id]);

        PayrollRunChanged::dispatch($run, 'reviewed', $by->id);

        return $run;
    }
}
