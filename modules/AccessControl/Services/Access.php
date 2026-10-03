<?php

namespace Modules\AccessControl\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\Identity\Models\User;
use Modules\Shared\Contracts\OrgHierarchy;
use Modules\Shared\Enums\Scope;

/**
 * Scoped permission checks. A permission is held at a scope (own/team/department/all);
 * a subject employee is covered when it falls inside that scope for the actor.
 */
final class Access
{
    /**
     * Resolved per call: the hierarchy is request-scoped while Access is a singleton.
     */
    private function org(): OrgHierarchy
    {
        return app(OrgHierarchy::class);
    }

    public function can(User $user, string $key, Model|int|null $subject = null): bool
    {
        if ($user->isSuper()) {
            return true;
        }

        $scope = $this->scope($user, $key);

        if ($scope === null) {
            return false;
        }

        if ($subject === null) {
            return true;
        }

        $employeeId = $subject instanceof Model ? (int) $subject->getKey() : $subject;

        return $this->covers($user, $scope, $employeeId);
    }

    public function scope(User $user, string $key): ?Scope
    {
        if ($user->isSuper()) {
            return Scope::All;
        }

        return $user->permissionScopes()[$key] ?? null;
    }

    /**
     * Employee ids the user may act on for this permission. Null means all.
     *
     * @return list<int>|null
     */
    public function visibleEmployeeIds(User $user, string $key): ?array
    {
        $scope = $this->scope($user, $key);

        if ($scope === Scope::All) {
            return null;
        }

        if ($scope === null) {
            return [];
        }

        $actor = $this->org()->employeeIdForUser($user->id);

        if ($actor === null) {
            return [];
        }

        return match ($scope) {
            Scope::Own => [$actor],
            Scope::Team => $this->org()->teamEmployeeIds($actor),
            Scope::Department => $this->org()->departmentEmployeeIds($actor),
        };
    }

    private function covers(User $user, Scope $scope, int $employeeId): bool
    {
        if ($scope === Scope::All) {
            return true;
        }

        $actor = $this->org()->employeeIdForUser($user->id);

        if ($actor === null) {
            return false;
        }

        return match ($scope) {
            Scope::Own => $actor === $employeeId,
            Scope::Team => in_array($employeeId, $this->org()->teamEmployeeIds($actor), true),
            Scope::Department => in_array($employeeId, $this->org()->departmentEmployeeIds($actor), true),
        };
    }
}
