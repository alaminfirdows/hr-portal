<?php

use Modules\Identity\Models\User;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('name can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Test User'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->name)->toBe('Test User');
});

test('email cannot be changed from account settings', function () {
    $user = User::factory()->create();
    $email = $user->email;

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Test User', 'email' => 'other@example.com'])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->email)->toBe($email)
        ->and($user->email_verified_at)->not->toBeNull();
});

test('account cannot be self-deleted', function () {
    expect(fn () => route('profile.destroy'))->toThrow(RouteNotFoundException::class);
});
