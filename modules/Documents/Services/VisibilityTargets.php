<?php

namespace Modules\Documents\Services;

use Illuminate\Support\Facades\Gate;
use Modules\Documents\Enums\DocumentVisibility;
use Modules\Identity\Models\User;
use Modules\Shared\Contracts\OrgHierarchy;
use Modules\Shared\Contracts\ProjectMembership;
use Modules\Shared\Exceptions\DomainException;

/**
 * Fills and validates the department/team/project a document is addressed to.
 * Non-managers can only address their own department/team and projects they belong to.
 */
final class VisibilityTargets
{
    public function __construct(private readonly OrgHierarchy $org, private readonly ProjectMembership $projects) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function apply(User $actor, array $data): array
    {
        $visibility = DocumentVisibility::from((string) $data['visibility']);
        $manager = Gate::forUser($actor)->allows('documents.manage') && DocumentManagers::managesAll($actor);
        $employeeId = $this->org->employeeIdForUser($actor->id);

        if ($visibility === DocumentVisibility::Organization && ! $manager) {
            throw new DomainException(__('Only HR can publish documents to the whole organization.'), 'visibility');
        }

        $data['department_id'] = $data['team_id'] = $data['project_id'] = null;

        switch ($visibility) {
            case DocumentVisibility::Department:
                $own = $employeeId === null ? null : $this->org->departmentId($employeeId);
                $id = ($manager ? ($data['target_department_id'] ?? null) : null) ?? $own;
                $this->require($id !== null, 'department');
                $data['department_id'] = $id;
                break;

            case DocumentVisibility::Team:
                $own = $employeeId === null ? null : $this->org->teamId($employeeId);
                $id = ($manager ? ($data['target_team_id'] ?? null) : null) ?? $own;
                $this->require($id !== null, 'team');
                $data['team_id'] = $id;
                break;

            case DocumentVisibility::Project:
                $id = $data['target_project_id'] ?? null;
                $this->require($id !== null, 'project');

                if (! $manager && ($employeeId === null || ! $this->projects->isMember((int) $id, $employeeId))) {
                    throw new DomainException(__('You are not a member of that project.'), 'target_project_id');
                }

                $data['project_id'] = (int) $id;
                break;

            default:
                break;
        }

        unset($data['target_department_id'], $data['target_team_id'], $data['target_project_id']);

        if (! $manager) {
            // Not the actor's to set: leave an existing HR employee link untouched.
            unset($data['employee_id']);
        }

        return $data;
    }

    private function require(bool $ok, string $what): void
    {
        if (! $ok) {
            throw new DomainException(__('Choose the :what this document is visible to.', ['what' => $what]), 'visibility');
        }
    }
}
