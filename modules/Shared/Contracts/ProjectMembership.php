<?php

namespace Modules\Shared\Contracts;

/**
 * Project membership lookups. Implemented by the Projects module.
 */
interface ProjectMembership
{
    public function isMember(int $projectId, int $employeeId): bool;

    /**
     * @return list<int>
     */
    public function projectIdsFor(int $employeeId): array;
}
