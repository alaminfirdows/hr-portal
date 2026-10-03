<?php

namespace Modules\AccessControl\Actions;

use Modules\AccessControl\Events\RolesAssigned;
use Modules\AccessControl\Models\Role;
use Modules\Identity\Models\User;
use Modules\Shared\Exceptions\DomainException;

final class AssignRoles
{
    /**
     * @param  list<int>  $roleIds
     */
    public function handle(User $user, array $roleIds, ?User $by = null): void
    {
        $before = $this->slugs($user);

        $superIds = Role::query()->where('is_super', true)->pluck('id')->all();
        $currentSuperIds = $user->roles()->whereIn('roles.id', $superIds)->pluck('roles.id')->all();
        $newSuperIds = array_values(array_intersect($superIds, $roleIds));
        $superChanged = array_diff($currentSuperIds, $newSuperIds) !== [] || array_diff($newSuperIds, $currentSuperIds) !== [];

        if ($superChanged && $by !== null && ! $by->isSuper()) {
            throw new DomainException(__('Only a super administrator can grant or revoke the administrator role.'), 'roles');
        }

        if ($currentSuperIds !== [] && $newSuperIds === []) {
            if ($by !== null && $by->is($user)) {
                throw new DomainException(__('You cannot remove your own administrator role.'));
            }

            if ($user->is_active && ! $this->otherActiveSuperExists($user)) {
                throw new DomainException(__('At least one active super administrator is required.'), 'roles');
            }
        }

        $user->roles()->sync($roleIds);
        $user->flushPermissionCache();

        $after = $this->slugs($user);

        RolesAssigned::dispatch($user, $before, $after, $by);
    }

    private function otherActiveSuperExists(User $user): bool
    {
        return User::query()
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->whereHas('roles', fn ($q) => $q->where('is_super', true))
            ->exists();
    }

    /**
     * @return list<string>
     */
    private function slugs(User $user): array
    {
        return array_values($user->roles()->orderBy('slug')->get()->map(fn (Role $role) => $role->slug)->all());
    }
}
