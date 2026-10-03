<?php

namespace Modules\Shared\Null;

use Modules\Shared\Contracts\OrgHierarchy;

final class NullOrgHierarchy implements OrgHierarchy
{
    public function employeeIdForUser(int $userId): ?int
    {
        return null;
    }

    public function userIdForEmployee(int $employeeId): ?int
    {
        return null;
    }

    public function teamEmployeeIds(int $employeeId): array
    {
        return [$employeeId];
    }

    public function departmentEmployeeIds(int $employeeId): array
    {
        return [$employeeId];
    }

    public function managerUserId(int $employeeId): ?int
    {
        return null;
    }

    public function departmentId(int $employeeId): ?int
    {
        return null;
    }

    public function teamId(int $employeeId): ?int
    {
        return null;
    }

    public function employeeCount(string $attribute, int $id): int
    {
        return 0;
    }

    public function activeEmployeeIds(): array
    {
        return [];
    }
}
