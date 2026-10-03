<?php

use Modules\AccessControl\Models\Permission;
use Modules\AccessControl\Models\Role;
use Modules\AccessControl\Services\Access;
use Modules\Employees\Models\Employee;
use Modules\Shared\Enums\Scope;

test('super user passes every permission', function () {
    $admin = userWithRole('admin');

    expect($admin->can('payroll.manage'))->toBeTrue()
        ->and($admin->can('anything.unknown'))->toBeTrue();
});

test('scope own covers only own employee', function () {
    $user = userWithPermissions(['employees.view' => 'own']);
    $other = Employee::factory()->create();

    expect($user->can('employees.view', employeeOf($user)))->toBeTrue()
        ->and($user->can('employees.view', $other))->toBeFalse();
});

test('scope team covers direct and indirect reports', function () {
    $manager = userWithPermissions(['employees.view' => 'team']);
    $direct = Employee::factory()->create(['manager_id' => employeeOf($manager)->id]);
    $indirect = Employee::factory()->create(['manager_id' => $direct->id]);
    $stranger = Employee::factory()->create();
    forgetHierarchy();

    expect($manager->can('employees.view', $direct))->toBeTrue()
        ->and($manager->can('employees.view', $indirect))->toBeTrue()
        ->and($manager->can('employees.view', $stranger))->toBeFalse()
        ->and(app(Access::class)->visibleEmployeeIds($manager, 'employees.view'))
        ->toEqualCanonicalizing([employeeOf($manager)->id, $direct->id, $indirect->id]);
});

test('widest scope wins across roles', function () {
    $user = userWithPermissions(['employees.view' => 'own']);
    $role = Role::factory()->create();
    $role->permissions()->attach(Permission::query()->where('key', 'employees.view')->value('id'), ['scope' => 'all']);
    $user->roles()->attach($role);
    $user->flushPermissionCache();

    expect(app(Access::class)->scope($user->fresh(), 'employees.view'))->toBe(Scope::All);
});

test('user without permission is denied', function () {
    expect(userWithRole('employee')->can('payroll.manage'))->toBeFalse();
});

test('admin can create a role with scoped permissions', function () {
    $admin = userWithRole('admin');

    $this->actingAs($admin)->post(route('access.roles.store'), [
        'name' => 'Recruiter',
        'permissions' => ['employees.view' => 'department', 'employees.create' => 'all'],
    ])->assertSessionHasNoErrors();

    $role = Role::query()->where('slug', 'recruiter')->firstOrFail();
    expect($role->permissions()->count())->toBe(2);
});

test('system role cannot be deleted', function () {
    $admin = userWithRole('admin');

    $this->actingAs($admin)->delete(route('access.roles.destroy', Role::query()->where('slug', 'hr')->first()))
        ->assertSessionHasErrors();
    expect(Role::query()->where('slug', 'hr')->exists())->toBeTrue();
});

test('hr cannot manage roles by default', function () {
    $this->actingAs(userWithRole('hr'))->get(route('access.roles.index'))->assertForbidden();
});

test('roles index renders for admin', function () {
    $this->actingAs(userWithRole('admin'))->get(route('access.roles.index'))
        ->assertInertia(fn ($page) => $page->component('access/roles/index')->has('roles'));
});

test('access:sync removes stale permissions', function () {
    seedRoles();
    Permission::query()->create(['key' => 'old.perm', 'module' => 'Old', 'description' => 'x']);

    $this->artisan('access:sync')->assertSuccessful();

    expect(Permission::query()->where('key', 'old.perm')->exists())->toBeFalse();
});
