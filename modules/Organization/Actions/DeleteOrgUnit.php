<?php

namespace Modules\Organization\Actions;

use Illuminate\Database\Eloquent\Model;
use Modules\Organization\Events\OrganizationChanged;
use Modules\Organization\Models\Department;
use Modules\Organization\Models\Location;
use Modules\Organization\Models\Position;
use Modules\Organization\Models\Team;
use Modules\Shared\Contracts\OrgHierarchy;
use Modules\Shared\Exceptions\DomainException;

final class DeleteOrgUnit
{
    public function __construct(private readonly OrgHierarchy $org) {}

    public function handle(Model $model, string $labelAttribute, ?int $actorId = null): void
    {
        $attribute = match (true) {
            $model instanceof Department => 'department_id',
            $model instanceof Team => 'team_id',
            $model instanceof Position => 'position_id',
            $model instanceof Location => 'location_id',
            default => null,
        };

        if ($attribute !== null && $this->org->employeeCount($attribute, (int) $model->getKey()) > 0) {
            throw new DomainException(__('Cannot delete: employees are still assigned to it.'));
        }

        if ($model instanceof Department && $model->teams()->exists()) {
            throw new DomainException(__('Cannot delete: the department still has teams.'));
        }

        $before = $model->getAttributes();
        $model->delete();

        OrganizationChanged::dispatch($model, 'deleted', (string) $model->getAttribute($labelAttribute), $before, [], $actorId);
    }
}
