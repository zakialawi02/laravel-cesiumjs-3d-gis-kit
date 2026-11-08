<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);

test('each user holds exactly one role stored on the users table', function (): void {
    $user = User::query()->create([
        'name' => 'Role Default',
        'username' => 'role_default',
        'email' => 'role-default@example.com',
        'password' => 'password',
    ]);

    expect($user->fresh()->role)->toBe(UserRole::Engineering);
    expect(Schema::hasColumn('users', 'role'))->toBeTrue();
    expect(Schema::hasTable('role_user'))->toBeFalse();
    expect(class_exists('App\Models\Role'))->toBeFalse();
});

test('assigning a new role replaces the previous one', function (): void {
    $user = User::factory()->create();

    $user->update(['role' => UserRole::Admin]);

    expect($user->fresh()->role)->toBe(UserRole::Admin);
    expect($user->fresh()->hasRole('admin'))->toBeTrue();
    expect($user->fresh()->hasRole('engineering'))->toBeFalse();
    $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'admin']);
});

test('role checks return only exact assigned roles', function (bool $persisted): void {
    $user = $persisted ? User::factory()->create()->fresh() : User::factory()->make();

    expect($user->hasRole(['admin', 'engineering']))->toBeTrue();
    expect($user->hasRole(UserRole::Engineering))->toBeTrue();
    expect($user->hasRole([UserRole::Admin, 'engineering']))->toBeTrue();
    expect($user->hasRole('admin'))->toBeFalse();
    expect($user->hasRole('engineer'))->toBeFalse();
    expect($user->hasRole('superadmin'))->toBeFalse();
    expect($user->hasRole([]))->toBeFalse();
})->with(['persisted' => true, 'in memory' => false]);

test('only admins may upload data, manage data and manage users', function (string $state, bool $isAdmin): void {
    $user = $state === 'admin' ? User::factory()->admin()->make() : User::factory()->make();

    expect($user->isAdmin())->toBe($isAdmin);
    expect($user->isEngineering())->toBe(! $isAdmin);
    expect($user->canUploadData())->toBe($isAdmin);
    expect($user->canManageData())->toBe($isAdmin);
    expect($user->canManageUsers())->toBe($isAdmin);
})->with([
    'admin' => ['admin', true],
    'engineering' => ['engineering', false],
]);

test('permanently deleting a user removes their sessions', function (): void {
    $user = User::factory()->create();
    DB::table('sessions')->insert([
        'id' => 'session-of-deleted-user',
        'user_id' => $user->id,
        'payload' => '',
        'last_activity' => now()->getTimestamp(),
    ]);

    $user->forceDelete();

    $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    $this->assertModelMissing($user);
});

test('available role options are the enum cases with readable labels', function (): void {
    expect(UserRole::values())->toBe(['admin', 'engineering']);
    expect(UserRole::Admin->label())->toBe('Admin');
    expect(UserRole::Engineering->label())->toBe('Engineering');
});
