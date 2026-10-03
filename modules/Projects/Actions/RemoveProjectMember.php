<?php

namespace Modules\Projects\Actions;

use Modules\Identity\Models\User;
use Modules\Projects\Events\ProjectMemberChanged;
use Modules\Projects\Models\ProjectMember;
use Modules\Shared\Exceptions\DomainException;

final class RemoveProjectMember
{
    public function handle(ProjectMember $member, ?User $by = null): void
    {
        $member->loadMissing(['project', 'employee']);

        if ($member->project->manager_employee_id === $member->employee_id) {
            throw new DomainException(__('The project manager cannot be removed. Assign another manager first.'), 'employee_id');
        }

        $before = ['role' => $member->role->value, 'allocation_percent' => $member->allocation_percent];
        $member->delete();

        ProjectMemberChanged::dispatch($member->project, 'removed', $member->employee->full_name, $before, [], $by?->id);
    }
}
