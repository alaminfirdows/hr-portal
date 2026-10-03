<?php

use Illuminate\Support\Facades\Notification;
use Modules\Identity\Actions\InviteUser;
use Modules\Identity\Models\Invitation;
use Modules\Identity\Models\User;
use Modules\Identity\Notifications\InvitationNotification;

function inviteAndCaptureToken(string $email = 'new@example.com'): string
{
    Notification::fake();
    app(InviteUser::class)->handle('New Person', $email);
    $token = '';
    Notification::assertSentTo(User::query()->where('email', $email)->first(), InvitationNotification::class, function (InvitationNotification $n) use (&$token) {
        $token = $n->token;

        return true;
    });

    return $token;
}

test('admin can invite a user; account starts inactive', function () {
    Notification::fake();
    $admin = userWithRole('admin');

    $this->actingAs($admin)
        ->post(route('identity.users.store'), ['name' => 'Jane', 'email' => 'Jane@Example.com', 'roles' => ['employee']])
        ->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();
    expect($user->is_active)->toBeFalse()
        ->and($user->hasRole('employee'))->toBeTrue();
    Notification::assertSentTo($user, InvitationNotification::class);
});

test('employee cannot invite users', function () {
    $this->actingAs(userWithRole('employee'))
        ->post(route('identity.users.store'), ['name' => 'X', 'email' => 'x@example.com'])
        ->assertForbidden();
});

test('invitation can be accepted once and logs the user in', function () {
    $token = inviteAndCaptureToken();

    $this->get(route('invitations.accept', $token))->assertOk();

    $this->post(route('invitations.store', $token), ['password' => 'Secret-pass-123', 'password_confirmation' => 'Secret-pass-123'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
    $user = User::query()->where('email', 'new@example.com')->firstOrFail();
    expect($user->is_active)->toBeTrue()->and($user->email_verified_at)->not->toBeNull();

    auth()->logout();
    $this->post(route('invitations.store', $token), ['password' => 'Secret-pass-123', 'password_confirmation' => 'Secret-pass-123'])
        ->assertSessionHasErrors('token');
});

test('expired invitation is rejected', function () {
    $token = inviteAndCaptureToken();
    Invitation::query()->update(['expires_at' => now()->subMinute()]);

    $this->post(route('invitations.store', $token), ['password' => 'Secret-pass-123', 'password_confirmation' => 'Secret-pass-123'])
        ->assertSessionHasErrors('token');
    $this->assertGuest();
});

test('inactive user cannot log in', function () {
    $user = User::factory()->inactive()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('deactivated user is logged out on next request', function () {
    $user = userWithRole('employee');
    $user->forceFill(['is_active' => false])->save();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('admin cannot deactivate self', function () {
    $admin = userWithRole('admin');

    $this->actingAs($admin)->post(route('identity.users.deactivate', $admin))->assertSessionHasErrors();
    expect($admin->fresh()->is_active)->toBeTrue();
});

test('successful login records last login time', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

test('registration is disabled', function () {
    $this->get('/register')->assertNotFound();
});
