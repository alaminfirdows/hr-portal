<?php

namespace Modules\Employees\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Employees\Enums\EmployeeStatus;
use Modules\Employees\Events\EmployeeStatusChanged;
use Modules\Employees\Models\Employee;
use Modules\Employees\Services\HistoryRecorder;
use Modules\Identity\Actions\SetUserActive;
use Modules\Identity\Models\User;
use Modules\Shared\Exceptions\InvalidStateTransition;

/**
 * Lifecycle transitions. Suspended/terminated employees lose portal access.
 */
final class ChangeEmployeeStatus
{
    public function __construct(
        private readonly HistoryRecorder $history,
        private readonly SetUserActive $setUserActive,
    ) {}

    public function handle(Employee $employee, EmployeeStatus $to, ?User $by = null, ?string $reason = null, ?CarbonInterface $effectiveOn = null): Employee
    {
        $from = $employee->status;

        if (! $from->canTransitionTo($to)) {
            throw new InvalidStateTransition(__('Cannot change status from :from to :to.', ['from' => $from->label(), 'to' => $to->label()]), 'status');
        }

        DB::transaction(function () use ($employee, $from, $to, $by, $reason, $effectiveOn): void {
            $employee->status = $to;

            if ($to === EmployeeStatus::Terminated) {
                $employee->exited_on = ($effectiveOn ?? now())->toImmutable()->startOfDay();
            }

            $employee->save();
            $this->history->record($employee, ['status' => $from], ['status' => $to], $by?->id, $reason, $effectiveOn);

            $user = $employee->user;
            if ($user !== null) {
                if (in_array($to, [EmployeeStatus::Suspended, EmployeeStatus::Terminated], true)) {
                    $this->setUserActive->handle($user, false, $by);
                } elseif ($from === EmployeeStatus::Suspended && $to === EmployeeStatus::Active) {
                    $this->setUserActive->handle($user, true, $by);
                }
            }
        });

        EmployeeStatusChanged::dispatch($employee, $from, $to, $reason, $by?->id);

        return $employee;
    }
}
