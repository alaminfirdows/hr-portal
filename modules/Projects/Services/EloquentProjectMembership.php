<?php

namespace Modules\Projects\Services;

use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectMember;
use Modules\Shared\Contracts\ProjectMembership;

/**
 * Shared contract implementation: the project manager counts as a member.
 */
final class EloquentProjectMembership implements ProjectMembership
{
    public function isMember(int $projectId, int $employeeId): bool
    {
        return Project::query()->whereKey($projectId)->where('manager_employee_id', $employeeId)->exists()
            || ProjectMember::query()->where('project_id', $projectId)->where('employee_id', $employeeId)->exists();
    }

    public function projectIdsFor(int $employeeId): array
    {
        return array_values(Project::query()->involving($employeeId)->pluck('projects.id')->map(fn ($id) => (int) $id)->all());
    }
}
