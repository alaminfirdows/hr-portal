<?php

namespace Modules\Projects\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\AccessControl\Services\Access;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;
use Modules\Projects\Database\Factories\ProjectFactory;
use Modules\Projects\Enums\ProjectPriority;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Enums\ProjectVisibility;
use Modules\Shared\Contracts\OrgHierarchy;
use Modules\Shared\Enums\Scope;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property ProjectStatus $status
 * @property ProjectVisibility $visibility
 * @property int|null $manager_employee_id
 * @property string|null $client
 * @property ProjectPriority $priority
 * @property int|null $budget Minor units
 * @property CarbonImmutable|null $start_date
 * @property CarbonImmutable|null $end_date
 * @property-read Employee|null $manager
 * @property-read int|null $members_count
 */
#[Fillable([
    'name', 'code', 'description', 'status', 'visibility', 'manager_employee_id', 'client',
    'priority', 'budget', 'start_date', 'end_date',
])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'visibility' => ProjectVisibility::class,
            'priority' => ProjectPriority::class,
            'budget' => 'integer',
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
        ];
    }

    /**
     * Projects the user may see in lists: everything for projects.manage at scope all;
     * otherwise public + restricted (listed) plus any project they manage or belong to.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (app(Access::class)->scope($user, 'projects.manage') === Scope::All) {
            return;
        }

        $employeeId = app(OrgHierarchy::class)->employeeIdForUser($user->id);

        $query->where(fn (Builder $q) => $q
            ->whereIn('projects.visibility', [ProjectVisibility::Public->value, ProjectVisibility::Restricted->value])
            ->when($employeeId !== null, fn (Builder $q) => $q->orWhere(fn (Builder $q) => $q->involving($employeeId))));
    }

    /**
     * Projects the employee manages or belongs to.
     *
     * @param  Builder<self>  $query
     */
    public function scopeInvolving(Builder $query, int $employeeId): void
    {
        $query->where(fn (Builder $q) => $q
            ->where('projects.manager_employee_id', $employeeId)
            ->orWhereHas('members', fn (Builder $m) => $m->where('employee_id', $employeeId)));
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if ($term === null || trim($term) === '') {
            return;
        }

        $like = '%'.trim($term).'%';
        $query->where(fn (Builder $q) => $q
            ->where('projects.name', 'like', $like)
            ->orWhere('projects.code', 'like', $like)
            ->orWhere('projects.client', 'like', $like));
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_employee_id');
    }

    /**
     * @return HasMany<ProjectMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    /**
     * @return HasMany<ProjectFieldValue, $this>
     */
    public function fieldValues(): HasMany
    {
        return $this->hasMany(ProjectFieldValue::class);
    }
}
