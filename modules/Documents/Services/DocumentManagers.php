<?php

namespace Modules\Documents\Services;

use Modules\AccessControl\Services\Access;
use Modules\Identity\Models\User;
use Modules\Shared\Enums\Scope;

/**
 * Resolves documents.manage by scope. Only Scope::All manages every document; narrower scopes
 * manage just the documents linked to employees inside that scope.
 */
final class DocumentManagers
{
    /**
     * @return list<int>|null null means every employee (Scope::All)
     */
    public static function employeeIds(User $user): ?array
    {
        return app(Access::class)->visibleEmployeeIds($user, 'documents.manage');
    }

    public static function managesAll(User $user): bool
    {
        return app(Access::class)->scope($user, 'documents.manage') === Scope::All;
    }

    public static function managesEmployee(User $user, ?int $employeeId): bool
    {
        if (self::managesAll($user)) {
            return true;
        }

        return $employeeId !== null && in_array($employeeId, self::employeeIds($user) ?? [], true);
    }
}
