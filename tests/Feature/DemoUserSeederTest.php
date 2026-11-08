<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('the starter kit demo user seeder is not shipped with the application', function (): void {
    expect(class_exists('Database\Seeders\DemoUserSeeder'))->toBeFalse();
    expect(file_exists(database_path('seeders/DemoUserSeeder.php')))->toBeFalse();
});

test('the database seeder creates only the reference accounts and no demo batch', function (): void {
    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseCount('users', 2);
    expect(User::query()->orderBy('username')->pluck('username')->all())->toBe(['admin', 'engineering']);
});

test('the database seeder preserves existing users and adds no accounts once one exists', function (): void {
    $existing = User::factory()->create(['name' => 'Existing Person']);
    $originalAttributes = $existing->refresh()->getAttributes();

    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseCount('users', 1);
    expect($existing->fresh()->getAttributes())->toBe($originalAttributes);
});
