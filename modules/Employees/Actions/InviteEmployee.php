<?php

namespace Modules\Employees\Actions;

use Modules\Employees\Enums\EmployeeStatus;
use Modules\Employees\Models\Employee;
use Modules\Identity\Actions\InviteUser;
use Modules\Identity\Models\Invitation;
use Modules\Identity\Models\User;
use Modules\Shared\Exceptions\DomainException;

/**
 * Sends (or re-sends) a portal invitation for an existing employee record.
 */
final class InviteEmployee
{
    public function __construct(private readonly InviteUser $invite) {}

    /**
     * @param  list<string>  $roles
     */
    public function handle(Employee $employee, ?User $by = null, array $roles = ['employee']): Invitation
    {
        if ($employee->status === EmployeeStatus::Terminated) {
            throw new DomainException(__('Terminated employees cannot be invited.'));
        }

        if ($employee->user?->is_active) {
            throw new DomainException(__('This employee already has an active account.'));
        }

        $invitation = $this->invite->handle($employee->full_name, $employee->email, $by, $employee->user ? [] : $roles);
        $employee->forceFill(['user_id' => $invitation->user_id])->save();

        return $invitation;
    }
}
