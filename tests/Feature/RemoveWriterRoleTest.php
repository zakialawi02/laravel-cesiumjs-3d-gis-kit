<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('removing writer migrates users without another role to the regular user role', function (): void {
    $writer = Role::factory()->create(['name' => 'writer']);
    $user = User::factory()->hasAttached($writer)->create([
        'profile_photo_path' => '/assets/img/profile/writer.png',
    ]);
    $migration = require database_path('migrations/2026_10_16_055341_remove_writer_role.php');

    $migration->up();

    $this->assertModelExists($user);
    $this->assertDatabaseMissing('roles', ['name' => 'writer']);
    $this->assertDatabaseMissing('role_user', ['role_id' => $writer->id]);
    expect($user->fresh()->roles()->pluck('name')->all())->toBe(['user']);
    expect($user->fresh()->profile_photo_path)->toBe('/assets/img/profile/user.png');
});

test('removing writer preserves the other roles assigned to the user', function (): void {
    $writer = Role::factory()->create(['name' => 'writer']);
    $admin = Role::factory()->create(['name' => 'admin']);
    $user = User::factory()->hasAttached([$writer, $admin])->create();
    $migration = require database_path('migrations/2026_10_16_055341_remove_writer_role.php');

    $migration->up();

    expect($user->fresh()->roles()->pluck('name')->all())->toBe(['admin']);
    $this->assertDatabaseCount('role_user', 1);
    $this->assertDatabaseMissing('roles', ['name' => 'writer']);
});

test('seeding after role removal does not recreate writer', function (): void {
    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseMissing('roles', ['name' => 'writer']);
    $this->assertDatabaseMissing('users', ['username' => 'writer']);
    expect(User::getRoleOptions())->toBe(['admin', 'superadmin', 'user']);
});
