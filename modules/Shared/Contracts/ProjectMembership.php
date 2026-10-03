<?php

namespace Modules\Shared\Contracts;

use Modules\Identity\Models\User;

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

    /**
     * Projects the user may pick (e.g. as a document audience): every project they can see.
     *
     * @return list<array{value: int, label: string}>
     */
    public function optionsFor(User $user): array;
}
