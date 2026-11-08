<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

test('public registration is not available', function (): void {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'New User',
        'username' => 'new_user',
        'email' => 'new@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'admin',
    ])->assertNotFound();

    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
});

test('admin-created accounts get a UUID, the chosen role and a verified email without accepting unrelated attributes', function (): void {
    $admin = User::factory()->admin()->create();
    $password = Str::password(16);

    $this->actingAs($admin)->post(route('dashboard.users.store'), [
        'name' => 'New User',
        'username' => 'new_user',
        'email' => 'new@example.com',
        'role' => UserRole::Engineering->value,
        'password' => $password,
        'password_confirmation' => $password,
        'email_verified_at' => null,
        'profile_photo_path' => '/storage/untrusted.png',
    ])->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'new@example.com')->firstOrFail();
    expect(Str::isUuid($user->id))->toBeTrue();
    expect($user->role)->toBe(UserRole::Engineering);
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->profile_photo_path)->toBe('/assets/img/profile/user.png');
    $this->assertAuthenticatedAs($admin);
});

test('admin-created accounts reject invalid usernames', function (mixed $username, string $message): void {
    $admin = User::factory()->admin()->create();
    $password = Str::password(16);

    $this->actingAs($admin)->post(route('dashboard.users.store'), [
        'name' => 'New User',
        'username' => $username,
        'email' => 'new@example.com',
        'role' => UserRole::Engineering->value,
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertSessionHasErrors(['username' => $message]);

    $this->assertDatabaseCount('users', 1);
})->with([
    'missing' => [null, 'The username field is required.'],
    'blank' => ['   ', 'The username field is required.'],
    'array' => [['username'], 'The username field must be a string.'],
    'invalid characters' => ['user name', 'Username may only contain lowercase letters, numbers, dots, dashes and underscores.'],
    'non ASCII characters' => ['penggunaé', 'Username may only contain lowercase letters, numbers, dots, dashes and underscores.'],
    'too short' => ['ab', 'The username field must be at least 3 characters.'],
    'too long' => [str_repeat('a', 51), 'The username field must not be greater than 50 characters.'],
]);

test('admin-created accounts reject usernames belonging to active or deleted accounts', function (bool $deleted): void {
    $admin = User::factory()->admin()->create();
    $password = Str::password(16);
    $existingUser = User::factory()->create(['username' => 'reserved']);

    if ($deleted) {
        $existingUser->delete();
    }

    $this->actingAs($admin)->post(route('dashboard.users.store'), [
        'name' => 'New User',
        'username' => ' RESERVED ',
        'email' => 'new@example.com',
        'role' => UserRole::Engineering->value,
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertSessionHasErrors(['username' => 'The username has already been taken.']);

    $this->assertDatabaseCount('users', 2);
})->with(['active' => false, 'deleted' => true]);

test('profile updates preserve the user own username and cannot change the role', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/profile', [
        'name' => 'Updated Name',
        'username' => $user->username,
        'email' => $user->email,
        'role' => UserRole::Admin->value,
        'profile_photo_path' => '/storage/untrusted.png',
    ])->assertSessionHasNoErrors()->assertRedirect('/profile');

    expect($user->fresh()->role)->toBe(UserRole::Engineering);
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Updated Name',
        'username' => $user->username,
        'profile_photo_path' => '/assets/img/profile/user.png',
    ]);
});

test('profile updates reject another user username', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($user)->patch('/profile', [
        'name' => $user->name,
        'username' => $otherUser->username,
        'email' => $user->email,
    ])->assertSessionHasErrors(['username' => 'The username has already been taken.']);

    $this->assertDatabaseHas('users', ['id' => $user->id, 'username' => $user->username]);
});

test('profile updates reject array identifiers without a server error', function (string $field): void {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/profile', [
        'name' => $user->name,
        'username' => $user->username,
        'email' => $user->email,
        $field => [$field],
    ])->assertSessionHasErrors([$field => "The {$field} field must be a string."]);

    $this->assertDatabaseHas('users', ['id' => $user->id, 'username' => $user->username, 'email' => $user->email]);
})->with(['username', 'email']);

test('soft deleted users cannot log in and keep their role for restoration', function (string $identifierField): void {
    $user = User::factory()->admin()->create();
    $user->delete();

    $this->post('/login', ['id_user' => $user->{$identifierField}, 'password' => 'password'])
        ->assertSessionHasErrors(['id_user' => __('auth.failed')]);

    $this->assertGuest();
    $this->assertSoftDeleted($user);
    $user->restore();
    expect($user->fresh()->role)->toBe(UserRole::Admin);
})->with(['email', 'username']);

test('social login provider columns are not part of the users table', function (): void {
    expect(Schema::hasColumns('users', ['provider_name']))->toBeFalse();
    expect(Schema::hasColumns('users', ['provider_id']))->toBeFalse();
    expect(Schema::hasColumns('users', ['provider_token']))->toBeFalse();
    expect(Schema::hasColumns('users', ['provider_refresh_token']))->toBeFalse();
    expect(User::factory()->create()->toArray())->not->toHaveKeys(['password', 'remember_token']);
});

test('profile photos fall back to the default image regardless of role', function (string $state): void {
    $user = $state === 'admin' ? User::factory()->admin()->make() : User::factory()->make();
    $user->profile_photo_path = null;

    expect($user->profile_photo_url)->toBe(asset('/assets/img/profile/user.png'));
})->with(['admin', 'engineering']);

test('custom and default profile photos resolve to their configured URL', function (): void {
    $user = User::factory()->make();

    expect($user->profile_photo_path)->toBe('/assets/img/profile/user.png');
    expect($user->profile_photo_url)->toBe(asset('/assets/img/profile/user.png'));

    $user->profile_photo_path = '/storage/profiles/custom.png';

    expect($user->profile_photo_url)->toBe(asset('/storage/profiles/custom.png'));
});
