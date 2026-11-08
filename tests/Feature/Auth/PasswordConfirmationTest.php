<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(LazilyRefreshDatabase::class);

test('password confirmation routes are not registered', function (): void {
    expect(Route::has('password.confirm'))->toBeFalse();
});

test('the confirm password screen is not available', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/confirm-password')->assertNotFound();
});

test('posting to the confirm password endpoint is not available', function (string $password) {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/confirm-password', ['password' => $password])
        ->assertNotFound()
        ->assertSessionMissing('auth.password_confirmed_at');
})->with(['password', 'wrong-password']);
