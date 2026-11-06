<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(LazilyRefreshDatabase::class);

test('the demo seeder creates one hundred users with roles and mixed verification statuses', function (): void {
    $this->seed(DemoUserSeeder::class);

    $this->assertDatabaseCount('users', 100);
    $this->assertDatabaseCount('roles', 3);
    $this->assertDatabaseCount('role_user', 100);
    expect(Role::query()->where('name', 'superadmin')->firstOrFail()->users()->count())->toBe(5);
    expect(Role::query()->where('name', 'admin')->firstOrFail()->users()->count())->toBe(15);
    expect(Role::query()->where('name', 'user')->firstOrFail()->users()->count())->toBe(80);
    expect(User::query()->whereNotNull('email_verified_at')->count())->toBe(80);
    expect(User::query()->whereNull('email_verified_at')->count())->toBe(20);
    expect(Hash::check('password', User::query()->firstOrFail()->password))->toBeTrue();
    expect(User::query()->whereHas('roles', fn (Builder $query): Builder => $query->where('name', 'admin'))->firstOrFail()->profile_photo_path)->toBe(User::DEFAULT_PHOTO_PATHS[1]);
    expect(User::query()->whereHas('roles', fn (Builder $query): Builder => $query->where('name', 'user'))->firstOrFail()->profile_photo_path)->toBe(User::DEFAULT_PHOTO_PATHS[0]);
});

test('the demo seeder preserves existing user data and memberships', function (): void {
    $role = Role::factory()->create(['name' => 'editor']);
    $existing = User::factory()->hasAttached($role)->create(['name' => 'Existing Person']);
    $originalAttributes = $existing->refresh()->getAttributes();

    $this->seed(DemoUserSeeder::class);

    $this->assertDatabaseCount('users', 101);
    expect($existing->fresh()->getAttributes())->toBe($originalAttributes);
    expect($existing->fresh()->roles->pluck('id')->all())->toBe([$role->id]);
});

test('running the demo seeder again adds another batch and reuses existing roles', function (): void {
    $this->seed(DemoUserSeeder::class);

    $this->seed(DemoUserSeeder::class);

    $this->assertDatabaseCount('users', 200);
    $this->assertDatabaseCount('roles', 3);
    $this->assertDatabaseCount('role_user', 200);
});
