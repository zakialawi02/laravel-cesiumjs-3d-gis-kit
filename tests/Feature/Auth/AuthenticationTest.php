<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertOk()
        ->assertSee('Email or Username')
        ->assertSee('type="text" name="login"', escape: false);
});

test('users can authenticate using email or username', function (string $identifierField, bool $normalize): void {
    $user = User::factory()->create(['password' => 'Password-123']);
    $login = $normalize ? ' '.strtoupper($user->{$identifierField}).' ' : $user->{$identifierField};

    $response = $this->post('/login', [
        'login' => $login,
        'password' => 'Password-123',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('dashboard', absolute: false));
})->with([
    'email' => ['email', false],
    'username' => ['username', false],
    'normalized email' => ['email', true],
    'normalized username' => ['username', true],
]);

test('users can not authenticate with invalid password', function (string $identifierField): void {
    $user = User::factory()->create();

    $this->post('/login', [
        'login' => $user->{$identifierField},
        'password' => 'wrong-password',
    ])->assertSessionHasErrors(['login' => __('auth.failed')]);

    $this->assertGuest();
})->with(['email', 'username']);

test('unknown email addresses and usernames receive the same authentication error', function (string $login): void {
    $this->post('/login', ['login' => $login, 'password' => 'password'])
        ->assertSessionHasErrors(['login' => __('auth.failed')]);

    $this->assertGuest();
})->with(['missing@example.com', 'missing-user']);

test('login validates the identifier and password before authentication', function (array $payload, string $field, string $rule, array $parameters): void {
    $this->post('/login', $payload)
        ->assertSessionHasErrors([$field => __($rule, ['attribute' => $field, ...$parameters])]);

    $this->assertGuest();
})->with([
    'missing identifier' => [['password' => 'password'], 'login', 'validation.required', []],
    'empty identifier' => [['login' => '   ', 'password' => 'password'], 'login', 'validation.required', []],
    'non string identifier' => [['login' => ['admin'], 'password' => 'password'], 'login', 'validation.string', []],
    'long identifier' => [['login' => str_repeat('a', 256), 'password' => 'password'], 'login', 'validation.max.string', ['max' => 255]],
    'missing password' => [['login' => 'admin'], 'password', 'validation.required', []],
    'non string password' => [['login' => 'admin', 'password' => ['password']], 'password', 'validation.string', []],
]);

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});
