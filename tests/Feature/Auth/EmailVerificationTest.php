<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(LazilyRefreshDatabase::class);

test('email verification routes are not registered', function (string $routeName): void {
    expect(Route::has($routeName))->toBeFalse();
})->with(['verification.notice', 'verification.verify', 'verification.send']);

test('the email verification screen is not available', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get('/verify-email')->assertNotFound();
});

test('unverified users can use the application without verifying their email', function (string $path): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get($path)->assertOk();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
})->with(['/dashboard', '/profile']);
