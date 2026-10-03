<?php

namespace Modules\AccessControl\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $key
 * @property string $module
 * @property string $description
 * @property-read RolePermission $pivot
 */
#[Fillable(['key', 'module', 'description'])]
class Permission extends Model
{
    /**
     * @return BelongsToMany<Role, $this, RolePermission>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->using(RolePermission::class)->withPivot('scope');
    }
}
