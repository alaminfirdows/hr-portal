<?php

namespace Modules\AccessControl\Concerns;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\AccessControl\Models\Permission;
use Modules\AccessControl\Models\Role;
use Modules\Shared\Enums\Scope;

/**
 * @property-read Collection<int, Role> $roles
 */
trait HasRoles
{
    /** @var array<string, Scope>|null */
    protected ?array $resolvedPermissionScopes = null;

    protected ?bool $resolvedIsSuper = null;

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }

    public function isSuper(): bool
    {
        return $this->resolvedIsSuper ??= $this->roles->contains('is_super', true);
    }

    /**
     * Widest scope per permission key over all roles. Cached on the instance.
     *
     * @return array<string, Scope>
     */
    public function permissionScopes(): array
    {
        if ($this->resolvedPermissionScopes !== null) {
            return $this->resolvedPermissionScopes;
        }

        $scopes = [];

        $this->loadMissing('roles.permissions');

        foreach ($this->roles as $role) {
            /** @var Permission $permission */
            foreach ($role->permissions as $permission) {
                $scope = $permission->pivot->scope;
                $current = $scopes[$permission->key] ?? null;
                $scopes[$permission->key] = $current === null ? $scope : (Scope::widest($current, $scope) ?? $scope);
            }
        }

        return $this->resolvedPermissionScopes = $scopes;
    }

    /**
     * @return list<string>
     */
    public function permissionKeys(): array
    {
        return array_keys($this->permissionScopes());
    }

    /**
     * @param  list<string>  $slugs
     */
    public function syncRolesBySlug(array $slugs): void
    {
        $this->roles()->sync(Role::query()->whereIn('slug', $slugs)->pluck('id'));
        $this->flushPermissionCache();
    }

    public function flushPermissionCache(): void
    {
        $this->resolvedPermissionScopes = null;
        $this->resolvedIsSuper = null;
        $this->unsetRelation('roles');
    }
}
