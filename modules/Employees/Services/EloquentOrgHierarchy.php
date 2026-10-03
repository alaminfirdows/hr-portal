<?php

namespace Modules\Employees\Services;

use Modules\Employees\Enums\EmployeeStatus;
use Modules\Employees\Models\Employee;
use Modules\Organization\Models\Department;
use Modules\Organization\Models\Team;
use Modules\Shared\Contracts\OrgHierarchy;

/**
 * Reporting lines from the employees table. Results are memoized per instance (one request).
 */
final class EloquentOrgHierarchy implements OrgHierarchy
{
    /** @var array<string, mixed> */
    private array $memo = [];

    public function employeeIdForUser(int $userId): ?int
    {
        return $this->remember("user:{$userId}", fn () => Employee::query()->where('user_id', $userId)->value('id'));
    }

    public function userIdForEmployee(int $employeeId): ?int
    {
        return $this->remember("emp-user:{$employeeId}", fn () => Employee::query()->whereKey($employeeId)->value('user_id'));
    }

    public function teamEmployeeIds(int $employeeId): array
    {
        return $this->remember("team:{$employeeId}", function () use ($employeeId): array {
            $ids = [$employeeId];

            // All direct and indirect reports, breadth first.
            $frontier = [$employeeId];
            while ($frontier !== []) {
                $next = Employee::query()->whereIn('manager_id', $frontier)->pluck('id')->map(fn ($id) => (int) $id)->all();
                $next = array_values(array_diff($next, $ids));
                $ids = [...$ids, ...$next];
                $frontier = $next;
            }

            $ledTeams = Team::query()->where('lead_employee_id', $employeeId)->pluck('id');
            if ($ledTeams->isNotEmpty()) {
                $ids = [...$ids, ...Employee::query()->whereIn('team_id', $ledTeams)->pluck('id')->map(fn ($id) => (int) $id)->all()];
            }

            return array_values(array_unique($ids));
        });
    }

    public function departmentEmployeeIds(int $employeeId): array
    {
        return $this->remember("dept:{$employeeId}", function () use ($employeeId): array {
            $departments = Department::query()->where('head_employee_id', $employeeId)->pluck('id')->all();

            if ($own = $this->departmentId($employeeId)) {
                $departments[] = $own;
            }

            $members = $departments === []
                ? []
                : Employee::query()->whereIn('department_id', $departments)->pluck('id')->map(fn ($id) => (int) $id)->all();

            return array_values(array_unique([...$this->teamEmployeeIds($employeeId), ...$members]));
        });
    }

    public function managerUserId(int $employeeId): ?int
    {
        return $this->remember("mgr:{$employeeId}", function () use ($employeeId): ?int {
            $managerId = Employee::query()->whereKey($employeeId)->value('manager_id');

            return $managerId ? $this->userIdForEmployee((int) $managerId) : null;
        });
    }

    public function departmentId(int $employeeId): ?int
    {
        return $this->column($employeeId, 'department_id');
    }

    public function teamId(int $employeeId): ?int
    {
        return $this->column($employeeId, 'team_id');
    }

    public function employeeCount(string $attribute, int $id): int
    {
        return Employee::query()
            ->where($attribute, $id)
            ->where('status', '!=', EmployeeStatus::Terminated->value)
            ->count();
    }

    public function activeEmployeeIds(): array
    {
        return array_values(Employee::query()->employed()->pluck('id')->map(fn ($id) => (int) $id)->all());
    }

    private function column(int $employeeId, string $column): ?int
    {
        /** @var array{department_id: int|null, team_id: int|null}|null $row */
        $row = $this->remember("row:{$employeeId}", function () use ($employeeId): ?array {
            $e = Employee::query()->whereKey($employeeId)->first(['department_id', 'team_id']);

            return $e ? ['department_id' => $e->department_id, 'team_id' => $e->team_id] : null;
        });

        return $row[$column] ?? null;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $resolve
     * @return T
     */
    private function remember(string $key, callable $resolve): mixed
    {
        if (! array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $resolve();
        }

        return $this->memo[$key];
    }

    public function flush(): void
    {
        $this->memo = [];
    }
}
