<?php

namespace Modules\Employees\Listeners;

use Modules\Employees\Actions\ChangeEmployeeStatus;
use Modules\Employees\Enums\EmployeeStatus;
use Modules\Employees\Models\Employee;
use Modules\Identity\Events\InvitationAccepted;

final class ActivateInvitedEmployee
{
    public function __construct(private readonly ChangeEmployeeStatus $change) {}

    public function handle(InvitationAccepted $event): void
    {
        $employee = Employee::query()->where('user_id', $event->user->id)->first();

        if ($employee?->status === EmployeeStatus::Invited) {
            $this->change->handle($employee, EmployeeStatus::Active, $event->user, __('Invitation accepted'));
        }
    }
}
