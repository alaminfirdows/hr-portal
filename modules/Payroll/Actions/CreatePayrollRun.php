<?php

namespace Modules\Payroll\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Identity\Models\User;
use Modules\Payroll\Enums\PayrollRunStatus;
use Modules\Payroll\Events\PayrollRunChanged;
use Modules\Payroll\Models\PayrollRun;
use Modules\Shared\Exceptions\DomainException;

final class CreatePayrollRun
{
    public function handle(string $period, User $by): PayrollRun
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) !== 1) {
            throw new DomainException(__('Period must look like YYYY-MM.'), 'period');
        }

        if (CarbonImmutable::parse("{$period}-01")->gt(now()->startOfMonth())) {
            throw new DomainException(__('Payroll cannot be run for a future month.'), 'period');
        }

        if (PayrollRun::query()->where('period', $period)->exists()) {
            throw new DomainException(__('A payroll run already exists for :period.', ['period' => $period]), 'period');
        }

        try {
            $run = PayrollRun::query()->create(['period' => $period, 'status' => PayrollRunStatus::Draft, 'created_by' => $by->id]);
        } catch (UniqueConstraintViolationException) {
            throw new DomainException(__('A payroll run already exists for :period.', ['period' => $period]), 'period');
        }

        PayrollRunChanged::dispatch($run, 'created', $by->id);

        return $run;
    }
}
