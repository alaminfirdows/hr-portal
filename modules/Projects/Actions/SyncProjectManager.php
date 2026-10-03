<?php

namespace Modules\Projects\Actions;

use Modules\Projects\Enums\MemberRole;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectMember;

/**
 * Keeps the project manager listed as a lead member. A replaced manager stays on the
 * project as a regular member.
 */
final class SyncProjectManager
{
    public function handle(Project $project, ?int $previousManagerId = null): void
    {
        if ($previousManagerId !== null && $previousManagerId !== $project->manager_employee_id) {
            ProjectMember::query()
                ->where('project_id', $project->id)
                ->where('employee_id', $previousManagerId)
                ->where('role', MemberRole::Lead->value)
                ->update(['role' => MemberRole::Member->value]);
        }

        if ($project->manager_employee_id === null) {
            return;
        }

        $member = ProjectMember::query()->firstOrNew([
            'project_id' => $project->id,
            'employee_id' => $project->manager_employee_id,
        ]);

        if (! $member->exists) {
            $member->fill(['allocation_percent' => 0, 'joined_on' => now()->toDateString()]);
        }

        $member->role = MemberRole::Lead;
        $member->save();
    }
}
