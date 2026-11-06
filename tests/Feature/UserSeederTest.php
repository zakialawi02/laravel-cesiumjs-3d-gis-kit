<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

test('the database seeder creates the reference accounts and their roles', function (string $username, string $name, string $photo): void {
    $this->seed(DatabaseSeeder::class);

    $user = User::query()->where('email', $username.'@mail.com')->firstOrFail();

    expect($user->name)->toBe($name);
    expect($user->username)->toBe($username);
    expect(Str::isUuid($user->id))->toBeTrue();
    expect(Hash::check($username, $user->password))->toBeTrue();
    expect($user->hasVerifiedEmail())->toBeTrue();
    expect($user->roles()->pluck('name')->all())->toBe([$username]);
    expect($user->profile_photo_path)->toBe('/assets/img/profile/'.$photo.'.png');
    $this->assertDatabaseCount('users', 3);
    $this->assertDatabaseCount('roles', 3);
    $this->assertDatabaseCount('role_user', 3);
})->with([
    'superadmin' => ['superadmin', 'SuperAdmin User', 'admin'],
    'admin' => ['admin', 'Admin User', 'admin'],
    'user' => ['user', 'Regular User', 'user'],
]);

test('seeding twice preserves user identities and does not duplicate memberships', function (): void {
    $this->seed(DatabaseSeeder::class);
    $identities = User::query()->pluck('id', 'email')->all();

    $this->seed(DatabaseSeeder::class);

    expect(User::query()->pluck('id', 'email')->all())->toBe($identities);
    $this->assertDatabaseCount('users', 3);
    $this->assertDatabaseCount('roles', 3);
    $this->assertDatabaseCount('role_user', 3);
});

test('reseeding preserves changed account details and additional roles', function (): void {
    $this->seed(DatabaseSeeder::class);
    $user = User::query()->where('email', 'admin@mail.com')->firstOrFail();
    $user->update(['name' => 'Changed Name', 'username' => 'administrator', 'password' => 'changed-password']);
    $user->assignRole('user');
    $passwordHash = $user->password;

    $this->seed(DatabaseSeeder::class);

    expect($user->fresh()->password)->toBe($passwordHash);
    expect($user->fresh()->roles()->pluck('name')->all())->toEqualCanonicalizing(['admin', 'user']);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Changed Name', 'username' => 'administrator']);
    $this->assertDatabaseCount('users', 3);
    $this->assertDatabaseCount('role_user', 4);
});

test('reseeding does not restore a deleted reference account', function (): void {
    $this->seed(DatabaseSeeder::class);
    $user = User::query()->where('email', 'user@mail.com')->firstOrFail();
    $user->delete();

    $this->seed(DatabaseSeeder::class);

    $this->assertSoftDeleted($user);
    $this->assertDatabaseCount('users', 3);
    $this->assertDatabaseCount('role_user', 3);
});
