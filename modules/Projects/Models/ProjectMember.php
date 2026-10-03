<?php

namespace Modules\Projects\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Employees\Models\Employee;
use Modules\Projects\Database\Factories\ProjectMemberFactory;
use Modules\Projects\Enums\MemberRole;

/**
 * @property int $id
 * @property int $project_id
 * @property int $employee_id
 * @property MemberRole $role
 * @property int $allocation_percent
 * @property CarbonImmutable $joined_on
 * @property-read Project $project
 * @property-read Employee $employee
 */
#[Fillable(['project_id', 'employee_id', 'role', 'allocation_percent', 'joined_on'])]
class ProjectMember extends Model
{
    /** @use HasFactory<ProjectMemberFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'role' => MemberRole::class,
            'allocation_percent' => 'integer',
            'joined_on' => 'immutable_date',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
