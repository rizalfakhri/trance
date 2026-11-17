<?php

test('auth screens remember a local redirect path as the intended url', function (string $screen) {
    $this->get("/{$screen}?redirect=".urlencode('/'))
        ->assertOk()
        ->assertSessionHas('url.intended', url('/'));
})->with(['login', 'register']);

test('auth screens ignore redirect paths pointing to another host', function (string $redirectPath) {
    $this->get('/login?redirect='.urlencode($redirectPath))
        ->assertOk()
        ->assertSessionMissing('url.intended');
})->with([
    'absolute url' => 'https://evil.example/phish',
    'protocol-relative url' => '//evil.example/phish',
    'backslash url' => '/\\evil.example/phish',
]);

test('new users are redirected to the intended url after registering', function () {
    $response = $this->withSession(['url.intended' => url('/')])->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/');
});
