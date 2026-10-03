<?php

namespace Modules\AccessControl\Actions;

use Modules\AccessControl\Models\Permission;
use Modules\Shared\Support\ModuleRegistry;

/**
 * Mirrors permission keys declared by modules into the permissions table.
 */
final class SyncPermissions
{
    public function __construct(private readonly ModuleRegistry $registry) {}

    /**
     * @return array{created: int, removed: int}
     */
    public function handle(): array
    {
        $declared = $this->registry->permissions();
        $created = 0;

        foreach ($declared as $key => $meta) {
            $permission = Permission::query()->updateOrCreate(
                ['key' => $key],
                ['module' => $meta['module'], 'description' => $meta['description']],
            );

            $created += $permission->wasRecentlyCreated ? 1 : 0;
        }

        $removed = Permission::query()->whereNotIn('key', array_keys($declared))->delete();

        return ['created' => $created, 'removed' => (int) $removed];
    }
}
