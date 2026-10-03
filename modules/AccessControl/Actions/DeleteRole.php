<?php

namespace Modules\AccessControl\Actions;

use Modules\AccessControl\Models\Role;
use Modules\Shared\Exceptions\DomainException;

final class DeleteRole
{
    public function handle(Role $role): void
    {
        if ($role->is_system) {
            throw new DomainException(__('System roles cannot be deleted.'));
        }

        if ($role->users()->exists()) {
            throw new DomainException(__('Remove this role from all users first.'));
        }

        $role->delete();
    }
}
