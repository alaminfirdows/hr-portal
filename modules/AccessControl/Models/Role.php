<?php

namespace Modules\AccessControl\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\AccessControl\Database\Factories\RoleFactory;
use Modules\Identity\Models\User;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_super
 * @property bool $is_system
 * @property-read Collection<int, Permission> $permissions
 */
#[Fillable(['name', 'slug', 'description', 'is_super', 'is_system'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_super' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Permission, $this, RolePermission>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->using(RolePermission::class)->withPivot('scope');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
