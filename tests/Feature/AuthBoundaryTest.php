<?php

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(LazilyRefreshDatabase::class);

test('guests are redirected to login from protected pages', function (string $path): void {
    $this->get($path)->assertRedirect(route('login'));
})->with(['/', '/dashboard', '/profile']);

test('authenticated users can open the dashboard', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard')->assertOk();
});

test('authenticated users are redirected away from guest pages', function (string $path): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get($path)->assertRedirect(route('dashboard'));
})->with(['/login', '/forgot-password']);

test('login is blocked after five failed attempts', function (string $identifierField): void {
    $user = User::factory()->create();
    Event::fake([Lockout::class]);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post('/login', [
            'id_user' => $user->{$identifierField},
            'password' => 'wrong-password',
        ])->assertSessionHasErrors(['id_user' => __('auth.failed')]);
    }

    $this->post('/login', [
        'id_user' => ' '.strtoupper($user->{$identifierField}).' ',
        'password' => 'password',
    ])->assertSessionHasErrors('id_user');

    $this->assertGuest();
    Event::assertDispatched(Lockout::class);
})->with(['email', 'username']);

test('public registration is not available', function (): void {
    User::factory()->create();

    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'Another User',
        'username' => 'another_user',
        'email' => 'another@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
    $this->assertDatabaseCount('users', 1);
});

test('email verification and password confirmation pages are not available', function (string $path): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get($path)->assertNotFound();
})->with(['/verify-email', '/confirm-password']);

test('dashboard escapes the authenticated user name', function (): void {
    $user = User::factory()->create(['name' => '<script>alert(1)</script>']);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertSee($user->name)
        ->assertDontSee($user->name, escape: false);
});
