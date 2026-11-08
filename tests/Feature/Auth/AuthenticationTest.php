<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertOk()
        ->assertSee('Username or Email')
        ->assertSee('name="id_user"', escape: false);
});

test('users can authenticate using email or username', function (string $identifierField, bool $normalize): void {
    $user = User::factory()->create(['password' => 'Password-123']);
    $identifier = $normalize ? ' '.strtoupper($user->{$identifierField}).' ' : $user->{$identifierField};

    $response = $this->post('/login', [
        'id_user' => $identifier,
        'password' => 'Password-123',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('home', absolute: false));
})->with([
    'email' => ['email', false],
    'username' => ['username', false],
    'normalized email' => ['email', true],
    'normalized username' => ['username', true],
]);

test('users can not authenticate with invalid password', function (string $identifierField): void {
    $user = User::factory()->create();

    $this->post('/login', [
        'id_user' => $user->{$identifierField},
        'password' => 'wrong-password',
    ])->assertSessionHasErrors(['id_user' => __('auth.failed')]);

    $this->assertGuest();
})->with(['email', 'username']);

test('unknown email addresses and usernames receive the same authentication error', function (string $identifier): void {
    $this->post('/login', ['id_user' => $identifier, 'password' => 'password'])
        ->assertSessionHasErrors(['id_user' => __('auth.failed')]);

    $this->assertGuest();
})->with(['missing@example.com', 'missing-user']);

test('login validates the identifier and password before authentication', function (array $payload, string $field, string $rule, array $parameters): void {
    $attribute = str_replace('_', ' ', $field);

    $this->post('/login', $payload)
        ->assertSessionHasErrors([$field => __($rule, ['attribute' => $attribute, ...$parameters])]);

    $this->assertGuest();
})->with([
    'missing identifier' => [['password' => 'password'], 'id_user', 'validation.required', []],
    'empty identifier' => [['id_user' => '   ', 'password' => 'password'], 'id_user', 'validation.required', []],
    'non string identifier' => [['id_user' => ['admin'], 'password' => 'password'], 'id_user', 'validation.string', []],
    'long identifier' => [['id_user' => str_repeat('a', 256), 'password' => 'password'], 'id_user', 'validation.max.string', ['max' => 255]],
    'missing password' => [['id_user' => 'admin'], 'password', 'validation.required', []],
    'non string password' => [['id_user' => 'admin', 'password' => ['password']], 'password', 'validation.string', []],
]);

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect(route('login'));
});
