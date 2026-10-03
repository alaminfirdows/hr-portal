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

        if ($by !== null && $by->is($user)) {
            $superIds = Role::query()->where('is_super', true)->pluck('id')->all();
            $hadSuper = $user->roles()->whereIn('roles.id', $superIds)->exists();

            if ($hadSuper && array_intersect($superIds, $roleIds) === []) {
                throw new DomainException(__('You cannot remove your own administrator role.'));
            }
        }

        $user->roles()->sync($roleIds);
        $user->flushPermissionCache();

        $after = $this->slugs($user);

        RolesAssigned::dispatch($user, $before, $after, $by);
    }

    /**
     * @return list<string>
     */
    private function slugs(User $user): array
    {
        return array_values($user->roles()->orderBy('slug')->get()->map(fn (Role $role) => $role->slug)->all());
    }
}
