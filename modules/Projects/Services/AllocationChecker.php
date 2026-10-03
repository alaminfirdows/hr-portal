<?php

namespace Modules\Projects\Services;

use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Models\ProjectMember;

/**
 * Sums an employee's allocation across open (planned, active, on hold) projects.
 */
final class AllocationChecker
{
    public const LIMIT = 100;

    public function total(int $employeeId): int
    {
        return (int) ProjectMember::query()
            ->where('employee_id', $employeeId)
            ->whereHas('project', fn ($q) => $q->whereIn('status', ProjectStatus::openValues()))
            ->sum('allocation_percent');
    }

    public function isOver(int $employeeId): bool
    {
        return $this->total($employeeId) > self::LIMIT;
    }

    /**
     * Totals for many employees at once.
     *
     * @param  list<int>  $employeeIds
     * @return array<int, int>
     */
    public function totals(array $employeeIds): array
    {
        return ProjectMember::query()
            ->selectRaw('employee_id, SUM(allocation_percent) as total')
            ->whereIn('employee_id', $employeeIds)
            ->whereHas('project', fn ($q) => $q->whereIn('status', ProjectStatus::openValues()))
            ->groupBy('employee_id')
            ->pluck('total', 'employee_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }
}
