<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

test('registration creates a UUID user with only the default role and sends verification', function (): void {
    Role::factory()->create(['name' => 'admin']);
    Notification::fake();

    $this->post('/register', [
        'name' => 'New User',
        'username' => 'new_user',
        'email' => 'new@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'admin',
        'roles' => ['admin'],
        'email_verified_at' => now(),
        'provider_name' => 'untrusted-provider',
    ])->assertSessionHasNoErrors()->assertRedirect('/dashboard');

    $user = User::query()->where('email', 'new@example.com')->firstOrFail();
    $this->assertAuthenticatedAs($user);
    expect(Str::isUuid($user->id))->toBeTrue();
    expect($user->roles()->pluck('name')->all())->toBe(['user']);
    expect($user->hasVerifiedEmail())->toBeFalse();
    expect($user->provider_name)->toBeNull();
    Notification::assertSentTo($user, VerifyEmail::class);
    $this->get('/dashboard')->assertRedirect(route('verification.notice'));
});

test('registration rejects invalid usernames', function (mixed $username, string $rule, array $parameters): void {
    $this->post('/register', [
        'name' => 'New User',
        'username' => $username,
        'email' => 'new@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors(['username' => __($rule, ['attribute' => 'username', ...$parameters])]);

    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('role_user', 0);
})->with([
    'missing' => [null, 'validation.required', []],
    'not a string' => [123, 'validation.string', []],
    'blank' => ['   ', 'validation.required', []],
    'array' => [['username'], 'validation.string', []],
    'hyphen in the middle' => ['user-name', 'validation.regex', []],
    'leading hyphen' => ['-username', 'validation.regex', []],
    'trailing hyphen' => ['username-', 'validation.regex', []],
    'invalid characters' => ['user name', 'validation.regex', []],
    'non ASCII characters' => ['penggunaé', 'validation.regex', []],
    'too long' => [str_repeat('a', 256), 'validation.max.string', ['max' => 255]],
]);

test('registration rejects usernames belonging to active or deleted accounts', function (bool $deleted): void {
    $existingUser = User::factory()->create(['username' => 'reserved']);

    if ($deleted) {
        $existingUser->delete();
    }

    $this->post('/register', [
        'name' => 'New User',
        'username' => ' RESERVED ',
        'email' => 'new@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors(['username' => __('validation.unique', ['attribute' => 'username'])]);

    $this->assertGuest();
    $this->assertDatabaseCount('users', 1);
})->with(['active' => false, 'deleted' => true]);

test('profile updates preserve the user own username and cannot change roles or provider credentials', function (): void {
    $user = User::factory()->hasAttached(Role::factory()->create(['name' => 'user']))->create();
    Role::factory()->create(['name' => 'admin']);

    $this->actingAs($user)->patch('/profile', [
        'name' => 'Updated Name',
        'username' => $user->username,
        'email' => $user->email,
        'roles' => ['admin'],
        'provider_token' => 'untrusted-token',
    ])->assertSessionHasNoErrors()->assertRedirect('/profile');

    expect($user->fresh()->roles()->pluck('name')->all())->toBe(['user']);
    expect($user->fresh()->provider_token)->toBeNull();
    $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated Name', 'username' => $user->username]);
});

test('profile updates reject another user username', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($user)->patch('/profile', [
        'name' => $user->name,
        'username' => $otherUser->username,
        'email' => $user->email,
    ])->assertSessionHasErrors(['username' => __('validation.unique', ['attribute' => 'username'])]);

    $this->assertDatabaseHas('users', ['id' => $user->id, 'username' => $user->username]);
});

test('soft deleted users cannot log in and keep their roles for restoration', function (string $identifierField): void {
    $user = User::factory()->hasAttached(Role::factory()->create(['name' => 'user']))->create();
    $user->delete();

    $this->post('/login', ['login' => $user->{$identifierField}, 'password' => 'password'])
        ->assertSessionHasErrors(['login' => __('auth.failed')]);

    $this->assertGuest();
    $this->assertSoftDeleted($user);
    $user->restore();
    expect($user->hasRole('user'))->toBeTrue();
})->with(['email', 'username']);

test('provider tokens are encrypted and hidden while provider accounts may omit a password', function (): void {
    $user = User::factory()->create();

    $user->forceFill([
        'password' => null,
        'provider_name' => 'google',
        'provider_id' => 'provider-user-id',
        'provider_token' => 'access-token',
        'provider_refresh_token' => 'refresh-token',
    ])->save();

    $user->refresh();
    expect($user->provider_token)->toBe('access-token');
    expect($user->provider_refresh_token)->toBe('refresh-token');
    expect($user->getRawOriginal('provider_token'))->not->toBe('access-token');
    expect($user->getRawOriginal('provider_refresh_token'))->not->toBe('refresh-token');
    expect($user->toArray())->not->toHaveKeys(['password', 'remember_token', 'provider_token', 'provider_refresh_token']);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'password' => null, 'provider_name' => 'google']);
});

test('profile photo fallback follows the assigned role', function (string $roleName, string $photo): void {
    $user = User::factory()->hasAttached(Role::factory()->create(['name' => $roleName]))->create();
    $user->profile_photo_path = null;

    expect($user->profile_photo_url)->toBe(asset('/assets/img/profile/'.$photo.'.png'));
})->with([
    'administrator' => ['admin', 'admin'],
    'super administrator' => ['superadmin', 'admin'],
    'regular user' => ['user', 'user'],
]);

test('custom and default profile photos resolve to their configured URL', function (): void {
    $user = User::factory()->make();

    expect($user->hasDefaultProfilePhoto())->toBeTrue();
    expect($user->profile_photo_url)->toBe(asset('/assets/img/profile/user.png'));

    $user->profile_photo_path = '/storage/profiles/custom.png';

    expect($user->hasDefaultProfilePhoto())->toBeFalse();
    expect($user->profile_photo_url)->toBe(asset('/storage/profiles/custom.png'));
});
