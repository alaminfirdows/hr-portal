<?php

namespace Modules\Shared\Contracts;

/**
 * Resolves reporting lines. Implemented by the Employees module.
 */
interface OrgHierarchy
{
    public function employeeIdForUser(int $userId): ?int;

    public function userIdForEmployee(int $employeeId): ?int;

    /**
     * The employee, direct reports, and members of teams the employee leads.
     *
     * @return list<int>
     */
    public function teamEmployeeIds(int $employeeId): array;

    /**
     * Team ids plus everyone in the employee's department and departments they head.
     *
     * @return list<int>
     */
    public function departmentEmployeeIds(int $employeeId): array;

    public function managerUserId(int $employeeId): ?int;

    public function departmentId(int $employeeId): ?int;

    public function teamId(int $employeeId): ?int;

    /**
     * Number of (non-terminated) employees assigned to an org unit.
     *
     * @param  'department_id'|'team_id'|'position_id'|'location_id'  $attribute
     */
    public function employeeCount(string $attribute, int $id): int;

    /**
     * Ids of employees that are currently employed (active, on leave, suspended).
     *
     * @return list<int>
     */
    public function activeEmployeeIds(): array;
}
