<?php

use Modules\Employees\Notifications\ProfileChangedNotification;

test('open never redirects to external hosts', function (string $url, string $expected) {
    $user = userWithRole('employee');
    $user->notifyNow(new ProfileChangedNotification(employeeOf($user), ['phone']));
    $n = $user->notifications()->firstOrFail();
    $n->forceFill(['data' => [...$n->data, 'url' => $url]])->save();

    $this->actingAs($user)->get(route('notifications.open', $n->id))
        ->assertRedirect($expected === 'index' ? route('notifications.index') : $expected);
})->with([
    'external' => ['https://evil.example/phish', 'index'],
    'protocol relative' => ['//evil.example/x', 'index'],
    'backslash' => ['/\\evil.example', 'index'],
    'relative' => ['/dashboard', '/dashboard'],
]);
