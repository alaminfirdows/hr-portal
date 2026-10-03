<?php

namespace Modules\Documents\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\AccessControl\Services\Access;
use Modules\Documents\Database\Factories\DocumentFactory;
use Modules\Documents\Enums\DocumentKind;
use Modules\Documents\Enums\DocumentVisibility;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;
use Modules\Organization\Models\Department;
use Modules\Organization\Models\Team;
use Modules\Shared\Contracts\OrgHierarchy;
use Modules\Shared\Contracts\ProjectMembership;

/**
 * @property int $id
 * @property int|null $folder_id
 * @property string $title
 * @property string|null $description
 * @property string|null $category
 * @property list<string>|null $tags
 * @property DocumentKind $kind
 * @property DocumentVisibility $visibility
 * @property int|null $department_id
 * @property int|null $team_id
 * @property int|null $project_id
 * @property int|null $employee_id
 * @property int|null $uploaded_by
 * @property string|null $file_path
 * @property string|null $file_name
 * @property string|null $mime_type
 * @property int|null $size
 * @property string|null $url
 * @property CarbonImmutable|null $expires_on
 * @property CarbonImmutable|null $notified_30_at
 * @property CarbonImmutable|null $notified_7_at
 * @property CarbonImmutable $created_at
 * @property-read Folder|null $folder
 * @property-read User|null $uploader
 * @property-read Employee|null $employee
 * @property-read Department|null $department
 * @property-read Team|null $team
 */
#[Fillable([
    'folder_id', 'title', 'description', 'category', 'tags', 'kind', 'visibility', 'department_id', 'team_id',
    'project_id', 'employee_id', 'uploaded_by', 'file_path', 'file_name', 'mime_type', 'size', 'url',
    'expires_on', 'notified_30_at', 'notified_7_at',
])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'kind' => DocumentKind::class,
            'visibility' => DocumentVisibility::class,
            'expires_on' => 'immutable_date',
            'notified_30_at' => 'immutable_datetime',
            'notified_7_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Folder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function sharedWith(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'document_shares', 'document_id', 'user_id')->withTimestamps();
    }

    public function isFile(): bool
    {
        return $this->kind === DocumentKind::File;
    }

    /**
     * Documents the user may open. Managers (documents.manage) see everything; everyone else sees
     * what they uploaded, what is addressed to them, and what was shared with them.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (app(Access::class)->scope($user, 'documents.manage') !== null) {
            return;
        }

        $org = app(OrgHierarchy::class);
        $employeeId = $org->employeeIdForUser($user->id);
        $departmentId = $employeeId === null ? null : $org->departmentId($employeeId);
        $teamId = $employeeId === null ? null : $org->teamId($employeeId);
        $projectIds = $employeeId === null ? [] : app(ProjectMembership::class)->projectIdsFor($employeeId);

        $query->where(function (Builder $q) use ($user, $employeeId, $departmentId, $teamId, $projectIds) {
            $q->where('uploaded_by', $user->id)
                ->orWhere('visibility', DocumentVisibility::Organization->value)
                ->orWhereIn('id', DocumentShare::query()->where('user_id', $user->id)->select('document_id'));

            if ($employeeId !== null) {
                $q->orWhere('employee_id', $employeeId);
            }

            if ($departmentId !== null) {
                $q->orWhere(fn (Builder $d) => $d->where('visibility', DocumentVisibility::Department->value)->where('department_id', $departmentId));
            }

            if ($teamId !== null) {
                $q->orWhere(fn (Builder $t) => $t->where('visibility', DocumentVisibility::Team->value)->where('team_id', $teamId));
            }

            if ($projectIds !== []) {
                $q->orWhere(fn (Builder $p) => $p->where('visibility', DocumentVisibility::Project->value)->whereIn('project_id', $projectIds));
            }
        });
    }
}
