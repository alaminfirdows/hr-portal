<?php

namespace Modules\Shared\Null;

use Modules\Identity\Models\User;
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

    public function optionsFor(User $user): array
    {
        return [];
    }
}
