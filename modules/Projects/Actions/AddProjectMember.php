<?php

namespace Modules\Projects\Actions;

use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;
use Modules\Projects\Enums\MemberRole;
use Modules\Projects\Events\ProjectMemberChanged;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectMember;
use Modules\Projects\Notifications\AddedToProject;
use Modules\Shared\Exceptions\DomainException;

final class AddProjectMember
{
    public function handle(Project $project, Employee $employee, MemberRole $role, int $allocation, ?User $by = null): ProjectMember
    {
        if ($project->manager_employee_id === $employee->id
            || ProjectMember::query()->where('project_id', $project->id)->where('employee_id', $employee->id)->exists()) {
            throw new DomainException(__(':name is already a member of this project.', ['name' => $employee->full_name]), 'employee_id');
        }

        if (! $employee->status->isEmployed()) {
            throw new DomainException(__(':name is not an active employee.', ['name' => $employee->full_name]), 'employee_id');
        }

        $member = ProjectMember::query()->create([
            'project_id' => $project->id,
            'employee_id' => $employee->id,
            'role' => $role,
            'allocation_percent' => $allocation,
            'joined_on' => now()->toDateString(),
        ]);

        ProjectMemberChanged::dispatch($project, 'added', $employee->full_name, [], [
            'role' => $role->value,
            'allocation_percent' => $allocation,
        ], $by?->id);

        if ($by !== null && $employee->user && ! $employee->user->is($by)) {
            $employee->user->notify(new AddedToProject($project, $role));
        }

        return $member;
    }
}
