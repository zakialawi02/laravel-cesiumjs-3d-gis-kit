<?php

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(LazilyRefreshDatabase::class);

test('guests are redirected to login from protected pages', function (string $path): void {
    $this->get($path)->assertRedirect(route('login'));
})->with(['/dashboard', '/profile', '/confirm-password', '/verify-email']);

test('authenticated users can open the dashboard', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard')->assertOk();
});

test('authenticated users are redirected away from guest pages', function (string $path): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get($path)->assertRedirect(route('dashboard'));
})->with(['/login', '/register']);

test('login is blocked after five failed attempts', function (string $identifierField): void {
    $user = User::factory()->create();
    Event::fake([Lockout::class]);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post('/login', [
            'login' => $user->{$identifierField},
            'password' => 'wrong-password',
        ])->assertSessionHasErrors(['login' => __('auth.failed')]);
    }

    $this->post('/login', [
        'login' => ' '.strtoupper($user->{$identifierField}).' ',
        'password' => 'password',
    ])->assertSessionHasErrors('login');

    $this->assertGuest();
    Event::assertDispatched(Lockout::class);
})->with(['email', 'username']);

test('registration rejects an existing email address', function (): void {
    $user = User::factory()->create();

    $this->post('/register', [
        'name' => 'Another User',
        'username' => 'another_user',
        'email' => ' '.strtoupper($user->email).' ',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors(['email' => __('validation.unique', ['attribute' => 'email'])]);

    $this->assertGuest();
    $this->assertDatabaseCount('users', 1);
});

test('registration rejects a password that is not confirmed', function (): void {
    $this->post('/register', [
        'name' => 'Test User',
        'username' => 'test_user',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'different-password',
    ])->assertSessionHasErrors(['password' => __('validation.confirmed', ['attribute' => 'password'])]);

    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
});

test('dashboard escapes the authenticated user name', function (): void {
    $user = User::factory()->create(['name' => '<script>alert(1)</script>']);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertSee($user->name)
        ->assertDontSee($user->name, escape: false);
});
