<?php

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(LazilyRefreshDatabase::class);

test('registration routes are not registered because accounts are created by an administrator', function (): void {
    expect(Route::has('register'))->toBeFalse();

    $this->get('/register')->assertNotFound();
});

test('posting registration data does not create or sign in an account', function (mixed $email): void {
    $this->post('/register', [
        'name' => 'Test User',
        'username' => 'test_user',
        'email' => $email,
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
})->with([
    'valid email' => ['test@example.com'],
    'mixed case email' => [' Test@Example.COM '],
    'array email' => [['test@example.com']],
]);

test('the login page points visitors to the administrator instead of a sign up link', function (): void {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Contact Admin')
        ->assertDontSee('/register');
});
