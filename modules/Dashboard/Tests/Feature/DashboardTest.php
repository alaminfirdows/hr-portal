<?php

test('guests are redirected to login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('hr sees headcount widget', function () {
    $this->actingAs(userWithRole('hr'))->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('dashboard/index')
            ->where('widgets', fn ($w) => collect($w)->pluck('key')->contains('employees.headcount')));
});

test('employee does not see headcount widget', function () {
    $this->actingAs(userWithRole('employee'))->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->component('dashboard/index')
            ->where('widgets', fn ($w) => ! collect($w)->pluck('key')->contains('employees.headcount')));
});

test('navigation is filtered by permission', function () {
    $this->actingAs(userWithRole('employee'))->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('navigation', function ($groups) {
            $titles = collect($groups)->flatMap(fn ($g) => collect($g['items'])->pluck('title'));

            return $titles->contains('Directory') && ! $titles->contains('Audit log') && ! $titles->contains('Roles');
        }));
});
