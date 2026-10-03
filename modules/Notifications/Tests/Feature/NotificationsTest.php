<?php

use Modules\Employees\Notifications\ProfileChangedNotification;
use Modules\Identity\Models\User;
use Modules\Notifications\Models\NotificationPreference;

function notifyProfileChanged(User $user): void
{
    $user->notifyNow(new ProfileChangedNotification(employeeOf($user), ['phone']));
}

test('in-app notification is stored and shared as unread count', function () {
    $user = userWithRole('employee');
    notifyProfileChanged($user);

    expect($user->unreadNotifications()->count())->toBe(1);
    $this->actingAs($user)->get(route('notifications.index'))
        ->assertInertia(fn ($page) => $page->component('notifications/index')->where('notifications.unread', 1));
});

test('open marks read and redirects to target', function () {
    $user = userWithRole('employee');
    notifyProfileChanged($user);
    $n = $user->notifications()->first();

    $this->actingAs($user)->get(route('notifications.open', $n->id))->assertRedirect();
    expect($n->fresh()->read_at)->not->toBeNull();
});

test('read all clears unread', function () {
    $user = userWithRole('employee');
    notifyProfileChanged($user);
    notifyProfileChanged($user);

    $this->actingAs($user)->post(route('notifications.read-all'));
    expect($user->unreadNotifications()->count())->toBe(0);
});

test('user cannot touch another user notification', function () {
    $owner = userWithRole('employee');
    notifyProfileChanged($owner);
    $n = $owner->notifications()->first();

    $this->actingAs(userWithRole('employee'))->post(route('notifications.read', $n->id))->assertNotFound();
});

test('disabling in-app channel suppresses database notification', function () {
    $user = userWithRole('employee');

    $this->actingAs($user)->put(route('notifications.preferences.update'), [
        'preferences' => [['type' => 'employees.profile_changed', 'in_app' => false, 'email' => false]],
    ])->assertSessionHasNoErrors();

    expect(NotificationPreference::query()->where('user_id', $user->id)->count())->toBe(1);
    notifyProfileChanged($user);
    expect($user->notifications()->count())->toBe(0);
});

test('unknown notification type rejected', function () {
    $this->actingAs(userWithRole('employee'))->put(route('notifications.preferences.update'), [
        'preferences' => [['type' => 'nope', 'in_app' => true, 'email' => true]],
    ])->assertSessionHasErrors();
});
