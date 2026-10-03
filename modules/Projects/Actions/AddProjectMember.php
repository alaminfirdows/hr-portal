<?php

namespace Modules\Projects\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;
use Modules\Projects\Enums\MemberRole;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Events\ProjectMemberChanged;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectMember;
use Modules\Projects\Notifications\AddedToProject;
use Modules\Shared\Exceptions\DomainException;

final class AddProjectMember
{
    public function handle(Project $project, Employee $employee, MemberRole $role, int $allocation, ?User $by = null): ProjectMember
    {
        $member = DB::transaction(function () use ($project, $employee, $role, $allocation): ProjectMember {
            // Serialize concurrent adds to the same project; the unique index is the last line of defence.
            $locked = Project::query()->lockForUpdate()->findOrFail($project->id);

            if ($locked->status === ProjectStatus::Archived) {
                throw new DomainException(__('Archived projects cannot take new members.'), 'employee_id');
            }

            if ($locked->manager_employee_id === $employee->id
                || ProjectMember::query()->where('project_id', $project->id)->where('employee_id', $employee->id)->exists()) {
                throw $this->duplicate($employee);
            }

            if (! $employee->status->isEmployed()) {
                throw new DomainException(__(':name is not an active employee.', ['name' => $employee->full_name]), 'employee_id');
            }

            try {
                return ProjectMember::query()->create([
                    'project_id' => $project->id,
                    'employee_id' => $employee->id,
                    'role' => $role,
                    'allocation_percent' => $allocation,
                    'joined_on' => now()->toDateString(),
                ]);
            } catch (UniqueConstraintViolationException) {
                throw $this->duplicate($employee);
            }
        });

        ProjectMemberChanged::dispatch($project, 'added', $employee->full_name, [], [
            'role' => $role->value,
            'allocation_percent' => $allocation,
        ], $by?->id);

        if ($by !== null && $employee->user && ! $employee->user->is($by)) {
            $employee->user->notify(new AddedToProject($project, $role));
        }

        return $member;
    }

    private function duplicate(Employee $employee): DomainException
    {
        return new DomainException(__(':name is already a member of this project.', ['name' => $employee->full_name]), 'employee_id');
    }
}
