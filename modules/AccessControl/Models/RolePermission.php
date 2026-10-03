<?php

namespace Modules\AccessControl\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Modules\Shared\Enums\Scope;

/**
 * @property int $role_id
 * @property int $permission_id
 * @property Scope $scope
 */
class RolePermission extends Pivot
{
    protected $table = 'permission_role';

    protected function casts(): array
    {
        return ['scope' => Scope::class];
    }
}
