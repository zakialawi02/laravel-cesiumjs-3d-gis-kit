<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

test('writer is not a supported role', function (): void {
    expect(UserRole::tryFrom('writer'))->toBeNull();
    expect(UserRole::values())->not->toContain('writer');
    expect(User::factory()->make()->hasRole('writer'))->toBeFalse();
    expect(Schema::hasTable('roles'))->toBeFalse();
    expect(file_exists(database_path('migrations/2026_10_16_055341_remove_writer_role.php')))->toBeFalse();
});

test('admins cannot create a user with the writer role', function (): void {
    $admin = User::factory()->admin()->create();
    $password = Str::password(16);

    $this->actingAs($admin)->post(route('dashboard.users.store'), [
        'name' => 'Writer Person',
        'username' => 'writer',
        'email' => 'writer@example.com',
        'role' => 'writer',
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertSessionHasErrors('role');

    $this->assertDatabaseMissing('users', ['username' => 'writer']);
});

test('seeding does not create a writer account', function (): void {
    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseMissing('users', ['username' => 'writer']);
    $this->assertDatabaseMissing('users', ['role' => 'writer']);
    expect(UserRole::values())->toBe(['admin', 'engineering']);
});
