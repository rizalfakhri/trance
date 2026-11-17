<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('new users are given the user role', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'admin',
    ]);

    expect(User::query()->sole()->role)->toBe(UserRole::User);
});

test('only admins may access the admin area', function (Closure $makeUser, bool $allowed) {
    expect(Gate::forUser($makeUser())->allows('access-admin'))->toBe($allowed);
})->with([
    'admin' => [fn () => User::factory()->admin()->create(), true],
    'user' => [fn () => User::factory()->create(), false],
]);
