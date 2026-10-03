<?php

namespace Modules\Reports\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\AccessControl\Services\Access;
use Modules\Identity\Models\User;
use Modules\Projects\Enums\ProjectVisibility;
use Modules\Reports\Contracts\Report;
use Modules\Shared\Contracts\OrgHierarchy;
use Modules\Shared\Enums\Scope;
use Modules\Shared\ValueObjects\Money;

abstract class AbstractReport implements Report
{
    public function permission(): string
    {
        return 'reports.view';
    }

    public function accessibleBy(User $user): bool
    {
        return Gate::forUser($user)->allows('reports.view') && Gate::forUser($user)->allows($this->permission());
    }

    public function chart(): ?array
    {
        return null;
    }

    /**
     * Live employees (soft-deleted excluded), limited to what the user may see under
     * reports.view and to the department filter when one is set.
     *
     * @param  array{from: string, to: string, department_id: int|null, year: int}  $filters
     */
    protected function employees(User $user, array $filters): Builder
    {
        $ids = app(Access::class)->visibleEmployeeIds($user, 'reports.view');

        return DB::table('employees')
            ->whereNull('employees.deleted_at')
            ->when($ids !== null, fn (Builder $q) => $q->whereIn('employees.id', $ids))
            ->when($filters['department_id'] !== null, fn (Builder $q) => $q->where('employees.department_id', $filters['department_id']));
    }

    /**
     * Limits a projects query to what ProjectAccess::canOpen allows: everything for projects.manage at
     * Scope::All, otherwise public projects and projects the viewer manages or belongs to.
     */
    protected function openableProjects(Builder $query, User $user, string $projects): void
    {
        if (app(Access::class)->scope($user, 'projects.manage') === Scope::All) {
            return;
        }

        $me = app(OrgHierarchy::class)->employeeIdForUser($user->id);

        $query->where(fn (Builder $v) => $v
            ->where("{$projects}.visibility", ProjectVisibility::Public->value)
            ->when($me !== null, fn (Builder $v) => $v
                ->orWhere("{$projects}.manager_employee_id", $me)
                ->orWhereIn("{$projects}.id", DB::table('project_members')->where('employee_id', $me)->select('project_id'))));
    }

    /**
     * @return array<int, string>
     */
    protected function departmentNames(): array
    {
        /** @var array<int, string> */
        return DB::table('departments')->pluck('name', 'id')->map(fn ($name) => (string) $name)->all();
    }

    /**
     * @param  array<int, string>  $names
     */
    protected function departmentLabel(?int $id, array $names): string
    {
        return $id === null ? __('Unassigned') : ($names[$id] ?? __('Unassigned'));
    }

    protected function money(int|string|null $minor): float
    {
        return Money::of((int) $minor)->toDecimal();
    }

    protected function fullName(string $first, string $last): string
    {
        return trim($first.' '.$last);
    }
}
