<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

test('the database seeder creates the reference accounts and their roles', function (string $username, string $name, UserRole $role, string $photo): void {
    $this->seed(DatabaseSeeder::class);

    $user = User::query()->where('email', $username.'@mail.com')->firstOrFail();

    expect($user->name)->toBe($name);
    expect($user->username)->toBe($username);
    expect(Str::isUuid($user->id))->toBeTrue();
    expect(Hash::check($username, $user->password))->toBeTrue();
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->role)->toBe($role);
    expect($user->profile_photo_path)->toBe('/assets/img/profile/'.$photo.'.png');
    $this->assertDatabaseCount('users', 2);
})->with([
    'admin' => ['admin', 'Admin User', UserRole::Admin, 'admin'],
    'engineering' => ['engineering', 'Engineering User', UserRole::Engineering, 'user'],
]);

test('seeding twice preserves user identities and does not duplicate accounts', function (): void {
    $this->seed(DatabaseSeeder::class);
    $identities = User::query()->pluck('id', 'email')->all();

    $this->seed(DatabaseSeeder::class);

    expect(User::query()->pluck('id', 'email')->all())->toBe($identities);
    $this->assertDatabaseCount('users', 2);
});

test('reseeding preserves changed account details and roles', function (): void {
    $this->seed(DatabaseSeeder::class);
    $user = User::query()->where('email', 'admin@mail.com')->firstOrFail();
    $user->update(['name' => 'Changed Name', 'username' => 'administrator', 'password' => Str::password(16), 'role' => UserRole::Engineering]);
    $passwordHash = $user->password;

    $this->seed(DatabaseSeeder::class);

    expect($user->fresh()->password)->toBe($passwordHash);
    expect($user->fresh()->role)->toBe(UserRole::Engineering);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Changed Name', 'username' => 'administrator']);
    $this->assertDatabaseCount('users', 2);
});

test('reseeding does not restore a deleted reference account', function (): void {
    $this->seed(DatabaseSeeder::class);
    $user = User::query()->where('email', 'engineering@mail.com')->firstOrFail();
    $user->delete();

    $this->seed(DatabaseSeeder::class);

    $this->assertSoftDeleted($user);
    $this->assertDatabaseCount('users', 2);
});
