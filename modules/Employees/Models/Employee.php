<?php

namespace Modules\Employees\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Employees\Database\Factories\EmployeeFactory;
use Modules\Employees\Enums\EmployeeStatus;
use Modules\Employees\Enums\EmploymentType;
use Modules\Employees\Enums\Gender;
use Modules\Employees\Enums\WorkType;
use Modules\Identity\Models\User;
use Modules\Organization\Models\Department;
use Modules\Organization\Models\Location;
use Modules\Organization\Models\Position;
use Modules\Organization\Models\Team;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $employee_code
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string|null $personal_email
 * @property string|null $phone
 * @property string|null $photo_path
 * @property CarbonImmutable|null $date_of_birth
 * @property Gender|null $gender
 * @property int|null $department_id
 * @property int|null $team_id
 * @property int|null $position_id
 * @property int|null $location_id
 * @property int|null $manager_id
 * @property CarbonImmutable $joined_on
 * @property CarbonImmutable|null $exited_on
 * @property EmployeeStatus $status
 * @property EmploymentType $employment_type
 * @property WorkType $work_type
 * @property string|null $address
 * @property array{name?: string, relation?: string, phone?: string}|null $emergency_contact
 * @property list<string>|null $skills
 * @property array<string, string>|null $links
 * @property-read string $full_name
 * @property-read User|null $user
 * @property-read Department|null $department
 * @property-read Team|null $team
 * @property-read Position|null $position
 * @property-read Location|null $location
 * @property-read Employee|null $manager
 */
#[Fillable([
    'user_id', 'employee_code', 'first_name', 'last_name', 'email', 'personal_email', 'phone', 'photo_path',
    'date_of_birth', 'gender', 'department_id', 'team_id', 'position_id', 'location_id', 'manager_id',
    'joined_on', 'exited_on', 'status', 'employment_type', 'work_type', 'address', 'emergency_contact',
    'skills', 'links',
])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Fields whose changes are written to employee_histories.
     */
    public const TRACKED = ['department_id', 'team_id', 'manager_id', 'position_id', 'status', 'employment_type'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'immutable_date',
            'joined_on' => 'immutable_date',
            'exited_on' => 'immutable_date',
            'gender' => Gender::class,
            'status' => EmployeeStatus::class,
            'employment_type' => EmploymentType::class,
            'work_type' => WorkType::class,
            'emergency_contact' => 'array',
            'skills' => 'array',
            'links' => 'array',
        ];
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->first_name} {$this->last_name}"));
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeEmployed(Builder $query): void
    {
        $query->whereIn('status', EmployeeStatus::employedValues());
    }

    /**
     * @param  Builder<self>  $query
     * @param  list<int>|null  $ids  null = no restriction
     */
    public function scopeWithinIds(Builder $query, ?array $ids): void
    {
        if ($ids !== null) {
            $query->whereIn('employees.id', $ids);
        }
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
            ->where('first_name', 'like', $like)
            ->orWhere('last_name', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orWhere('employee_code', 'like', $like));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    /**
     * @return HasMany<EmployeeHistory, $this>
     */
    public function histories(): HasMany
    {
        return $this->hasMany(EmployeeHistory::class)->latest('created_at')->latest('id');
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? route('employees.photo', $this) : null;
    }
}
