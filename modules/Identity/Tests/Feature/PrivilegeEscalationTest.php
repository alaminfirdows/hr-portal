<?php

use Illuminate\Support\Facades\Notification;
use Modules\AccessControl\Models\Role;
use Modules\Identity\Actions\AcceptInvitation;
use Modules\Identity\Actions\InviteUser;
use Modules\Identity\Actions\SetUserActive;
use Modules\Identity\Models\Invitation;
use Modules\Identity\Models\User;
use Modules\Identity\Notifications\InvitationNotification;
use Modules\Shared\Exceptions\DomainException;

test('users.manage without roles.manage cannot choose roles on invite', function () {
    Notification::fake();
    $hr = userWithRole('hr');

    $this->actingAs($hr)
        ->post(route('identity.users.store'), ['name' => 'Eve', 'email' => 'eve@example.com', 'roles' => ['admin']])
        ->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'eve@example.com')->firstOrFail();
    expect($user->isSuper())->toBeFalse()
        ->and($user->hasRole('admin'))->toBeFalse()
        ->and($user->hasRole('employee'))->toBeTrue();
});

test('non-super with roles.manage cannot invite with super role', function () {
    Notification::fake();
    seedRoles();
    $actor = userWithPermissions(['users.manage' => 'all', 'roles.manage' => 'all']);

    $this->actingAs($actor)
        ->post(route('identity.users.store'), ['name' => 'Eve', 'email' => 'eve@example.com', 'roles' => ['admin']])
        ->assertSessionHasErrors();

    expect(User::query()->where('email', 'eve@example.com')->exists())->toBeFalse();
});

test('super can invite with super role', function () {
    Notification::fake();

    $this->actingAs(userWithRole('admin'))
        ->post(route('identity.users.store'), ['name' => 'Eve', 'email' => 'eve@example.com', 'roles' => ['admin']])
        ->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'eve@example.com')->firstOrFail()->isSuper())->toBeTrue();
});

test('non-super cannot grant super role to self or others', function () {
    seedRoles();
    $actor = userWithPermissions(['users.manage' => 'all', 'roles.manage' => 'all']);
    $superId = Role::query()->where('is_super', true)->value('id');
    $other = userWithRole('employee');

    $this->actingAs($actor)->put(route('identity.users.roles', $actor), ['roles' => [$superId]])->assertSessionHasErrors();
    $this->actingAs($actor)->put(route('identity.users.roles', $other), ['roles' => [$superId]])->assertSessionHasErrors();

    expect($actor->fresh()->isSuper())->toBeFalse()
        ->and($other->fresh()->isSuper())->toBeFalse();
});

test('non-super cannot revoke super role', function () {
    seedRoles();
    $actor = userWithPermissions(['users.manage' => 'all', 'roles.manage' => 'all']);
    $admin = userWithRole('admin');
    userWithRole('admin');

    $this->actingAs($actor)->put(route('identity.users.roles', $admin), ['roles' => []])->assertSessionHasErrors();

    expect($admin->fresh()->isSuper())->toBeTrue();
});

test('last active super cannot lose super role', function () {
    $admin = userWithRole('admin');
    $other = userWithRole('admin');
    $other->forceFill(['is_active' => false])->save();
    $employeeRole = Role::query()->where('slug', 'employee')->value('id');

    $this->actingAs($admin)->put(route('identity.users.roles', $admin), ['roles' => [$employeeRole]])->assertSessionHasErrors();

    expect($admin->fresh()->isSuper())->toBeTrue();
});

test('non-super cannot edit the super role', function () {
    seedRoles();
    $actor = userWithPermissions(['roles.manage' => 'all']);
    $super = Role::query()->where('is_super', true)->firstOrFail();

    $this->actingAs($actor)->put(route('access.roles.update', $super), ['name' => 'Pwn', 'permissions' => []])->assertSessionHasErrors();

    expect($super->fresh()->name)->not->toBe('Pwn');
});

test('employee create with super role is rejected for non-super', function () {
    Notification::fake();
    seedRoles();
    $actor = userWithPermissions(['employees.create' => 'all', 'roles.manage' => 'all']);

    $this->actingAs($actor)->post(route('employees.store'), [
        'first_name' => 'Ada', 'last_name' => 'L', 'email' => 'ada@example.com', 'joined_on' => now()->toDateString(),
        'employment_type' => 'full_time', 'work_type' => 'onsite', 'roles' => ['admin'],
    ])->assertSessionHasErrors();

    expect(User::query()->where('email', 'ada@example.com')->exists())->toBeFalse();
});

test('cannot deactivate the last active super', function () {
    $admin = userWithRole('admin');
    $other = userWithRole('admin');
    $other->forceFill(['is_active' => false])->save();

    expect(fn () => app(SetUserActive::class)->handle($admin, false, null))->toThrow(DomainException::class);
    expect($admin->fresh()->is_active)->toBeTrue();
});

test('non-super cannot deactivate a super user', function () {
    $admin = userWithRole('admin');
    userWithRole('admin');
    $hr = userWithRole('hr');

    $this->actingAs($hr)->post(route('identity.users.deactivate', $admin))->assertSessionHasErrors();

    expect($admin->fresh()->is_active)->toBeTrue();
});

test('super can deactivate another super when one remains', function () {
    $a = userWithRole('admin');
    $b = userWithRole('admin');

    $this->actingAs($a)->post(route('identity.users.deactivate', $b))->assertSessionHasNoErrors();

    expect($b->fresh()->is_active)->toBeFalse();
});

test('accepting an invitation accepted meanwhile fails', function () {
    Notification::fake();
    app(InviteUser::class)->handle('New Person', 'new@example.com');
    $token = '';
    Notification::assertSentTo(User::query()->where('email', 'new@example.com')->first(), InvitationNotification::class, function (InvitationNotification $n) use (&$token) {
        $token = $n->token;

        return true;
    });
    $action = app(AcceptInvitation::class);

    $stale = $action->find($token);
    Invitation::query()->whereKey($stale->id)->update(['accepted_at' => now()]);

    expect(fn () => $action->handle($token, 'Password123!x'))->toThrow(DomainException::class);
});
