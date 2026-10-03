<?php

namespace Modules\Shared\Null;

use Modules\Shared\Contracts\ProjectMembership;

final class NullProjectMembership implements ProjectMembership
{
    public function isMember(int $projectId, int $employeeId): bool
    {
        return false;
    }

    public function projectIdsFor(int $employeeId): array
    {
        return [];
    }
}
