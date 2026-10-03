<?php

test('home redirects to dashboard', function () {
    $this->get('/')->assertRedirect('/dashboard');
});
