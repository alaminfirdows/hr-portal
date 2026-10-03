<?php

use Modules\AccessControl\Actions\SyncPermissions;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Models\Permission;
use Modules\AccessControl\Models\Role;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;

/*
|--------------------------------------------------------------------------
| Global test helpers
|--------------------------------------------------------------------------
*/

/**
 * Seed default roles + permission matrix once per test.
 */
function seedRoles(): void
{
    if (! Role::query()->exists()) {
        app(AccessControlSeeder::class)->run(app(SyncPermissions::class));
    }
}

/**
 * Active, verified user with a default role (admin, hr, manager, project_manager, employee, support)
 * and a linked employee record.
 *
 * @param  array<string, mixed>  $employee  employee attribute overrides
 */
function userWithRole(string $role, array $employee = []): User
{
    seedRoles();

    $user = User::factory()->create();
    $user->syncRolesBySlug([$role]);
    Employee::factory()->withUser($user)->create(['email' => $user->email, ...$employee]);
    forgetHierarchy();

    return $user->fresh() ?? $user;
}

/**
 * User with a custom role holding exactly these permissions.
 *
 * @param  array<string, string>  $permissions  key => scope
 * @param  array<string, mixed>  $employee
 */
function userWithPermissions(array $permissions, array $employee = []): User
{
    app(SyncPermissions::class)->handle();

    $role = Role::factory()->create();
    $ids = Permission::query()->whereIn('key', array_keys($permissions))->pluck('id', 'key');
    $role->permissions()->sync(collect($permissions)->mapWithKeys(fn ($scope, $key) => [$ids[$key] => ['scope' => $scope]])->all());

    $user = User::factory()->create();
    $user->roles()->attach($role);
    Employee::factory()->withUser($user)->create(['email' => $user->email, ...$employee]);
    forgetHierarchy();

    return $user->fresh() ?? $user;
}

function employeeOf(User $user): Employee
{
    return Employee::query()->where('user_id', $user->id)->firstOrFail();
}

/**
 * The hierarchy memoizes lookups for the request; reset it after changing reporting lines in a test.
 */
function forgetHierarchy(): void
{
    app()->forgetScopedInstances();
}
