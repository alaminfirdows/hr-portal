<?php

namespace Modules\Projects\Actions;

use Modules\Identity\Models\User;
use Modules\Projects\Enums\MemberRole;
use Modules\Projects\Events\ProjectMemberChanged;
use Modules\Projects\Models\ProjectMember;
use Modules\Shared\Exceptions\DomainException;

final class UpdateProjectMember
{
    public function handle(ProjectMember $member, MemberRole $role, int $allocation, ?User $by = null): ProjectMember
    {
        $member->loadMissing(['project', 'employee']);

        if ($member->project->manager_employee_id === $member->employee_id && $role !== MemberRole::Lead) {
            throw new DomainException(__('The project manager must keep the lead role. Change the manager first.'), 'role');
        }

        $before = ['role' => $member->role->value, 'allocation_percent' => $member->allocation_percent];
        $member->update(['role' => $role, 'allocation_percent' => $allocation]);
        $after = ['role' => $role->value, 'allocation_percent' => $allocation];

        if ($before !== $after) {
            ProjectMemberChanged::dispatch($member->project, 'updated', $member->employee->full_name, $before, $after, $by?->id);
        }

        return $member;
    }
}
