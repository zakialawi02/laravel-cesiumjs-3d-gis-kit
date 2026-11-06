<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(LazilyRefreshDatabase::class);

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    Event::fake([Registered::class]);

    $response = $this->post('/register', [
        'name' => 'Test User',
        'username' => 'test_user',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $this->assertDatabaseHas('users', ['name' => 'Test User',
        'username' => 'test_user', 'email' => 'test@example.com']);
    expect(Hash::check('password', User::where('email', 'test@example.com')->firstOrFail()->password))->toBeTrue();
    Event::assertDispatched(Registered::class);
});

test('registration normalizes email and username before validation while preserving name and password', function (): void {
    Notification::fake();

    $this->post('/register', [
        'name' => 'Test User',
        'username' => ' Test_USER ',
        'email' => ' Test@Example.COM ',
        'password' => ' Mixed-Password123! ',
        'password_confirmation' => ' Mixed-Password123! ',
    ])->assertSessionHasNoErrors()->assertRedirect('/dashboard');

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();
    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Test User',
        'username' => 'test_user',
        'email' => 'test@example.com',
    ]);
    expect(Hash::check(' Mixed-Password123! ', $user->password))->toBeTrue();
    Notification::assertSentTo($user, VerifyEmail::class);
});

test('registration rejects invalid email input after normalization', function (mixed $email, string $rule): void {
    $this->post('/register', [
        'name' => 'Test User',
        'username' => 'test_user',
        'email' => $email,
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors(['email' => __($rule, ['attribute' => 'email'])]);

    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
})->with([
    'blank' => ['   ', 'validation.required'],
    'array' => [['Test@Example.COM'], 'validation.string'],
    'not an email' => [' INVALID EMAIL ', 'validation.email'],
]);
