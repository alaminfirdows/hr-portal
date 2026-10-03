<?php

namespace Modules\AccessControl\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\AccessControl\Events\RoleSaved;
use Modules\AccessControl\Models\Permission;
use Modules\AccessControl\Models\Role;
use Modules\Identity\Models\User;
use Modules\Shared\Enums\Scope;
use Modules\Shared\Exceptions\DomainException;

final class SaveRole
{
    /**
     * @param  array<string, string>  $permissions  permission key => scope value
     */
    public function handle(?Role $role, string $name, ?string $description, array $permissions, ?User $by = null): Role
    {
        $before = $role ? $this->snapshot($role) : [];

        $role = DB::transaction(function () use ($role, $name, $description, $permissions): Role {
            $role ??= new Role(['slug' => $this->uniqueSlug($name)]);

            if ($role->is_super && $role->exists) {
                throw new DomainException(__('The super administrator role cannot be edited.'));
            }

            $role->fill(['name' => $name, 'description' => $description])->save();

            $ids = Permission::query()->whereIn('key', array_keys($permissions))->pluck('id', 'key');

            $sync = [];
            foreach ($permissions as $key => $scope) {
                if (isset($ids[$key])) {
                    $sync[$ids[$key]] = ['scope' => Scope::from($scope)->value];
                }
            }

            $role->permissions()->sync($sync);

            return $role;
        });

        RoleSaved::dispatch($role, $before, $this->snapshot($role->fresh() ?? $role), $by);

        return $role;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Role $role): array
    {
        $role->load('permissions');

        return [
            'name' => $role->name,
            'permissions' => $role->permissions->mapWithKeys(fn (Permission $p) => [$p->key => $p->pivot->scope->value])->sortKeys()->all(),
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name, '_');
        $slug = $base;
        $i = 2;

        while (Role::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}_{$i}";
            $i++;
        }

        return $slug;
    }
}
