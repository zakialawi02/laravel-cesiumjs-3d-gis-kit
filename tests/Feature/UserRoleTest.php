<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('a user can hold several roles and roles can belong to several users', function (): void {
    $admin = Role::factory()->create(['name' => 'admin']);
    $userRole = Role::factory()->create(['name' => 'user']);
    $user = User::factory()->create();
    $anotherUser = User::factory()->create();

    $user->assignRole($admin)->assignRole('user');
    $anotherUser->assignRole('user');

    expect($user->roles()->pluck('name')->all())->toEqualCanonicalizing(['admin', 'user']);
    expect($userRole->users()->pluck('users.id')->all())->toEqualCanonicalizing([$user->id, $anotherUser->id]);
    $this->assertDatabaseCount('role_user', 3);
});

test('assigning a role twice does not duplicate membership or discard other roles', function (): void {
    $role = Role::factory()->create(['name' => 'admin']);
    $user = User::factory()->hasAttached(Role::factory()->create(['name' => 'user']))->create();
    $user->load('roles');

    $user->assignRole($role)->assignRole('admin');

    expect($user->hasRole('admin'))->toBeTrue();
    expect($user->hasRole('user'))->toBeTrue();
    $this->assertDatabaseCount('role_user', 2);
});

test('removing a role preserves the remaining memberships', function (): void {
    $admin = Role::factory()->create(['name' => 'admin']);
    $userRole = Role::factory()->create(['name' => 'user']);
    $user = User::factory()->hasAttached([$admin, $userRole])->create();
    $user->load('roles');

    $user->removeRole('admin');

    expect($user->hasRole('admin'))->toBeFalse();
    expect($user->hasRole('user'))->toBeTrue();
    $this->assertDatabaseMissing('role_user', ['user_id' => $user->id, 'role_id' => $admin->id]);
    $this->assertDatabaseHas('role_user', ['user_id' => $user->id, 'role_id' => $userRole->id]);
});

test('role checks return only exact assigned roles', function (bool $eagerLoad): void {
    $user = User::factory()->hasAttached(Role::factory()->create(['name' => 'user']))->create();

    if ($eagerLoad) {
        $user->load('roles');
    }

    expect($user->hasAnyRole('admin', 'user'))->toBeTrue();
    expect($user->hasAnyRole('admin', 'superadmin'))->toBeFalse();
    expect($user->hasRole('use'))->toBeFalse();
    expect($user->hasAnyRole())->toBeFalse();
})->with(['loaded' => true, 'unloaded' => false]);

test('permanently deleting a user removes memberships and sessions while retaining roles', function (): void {
    $role = Role::factory()->create();
    $user = User::factory()->hasAttached($role)->create();
    config(['session.driver' => 'database']);
    $this->post('/login', ['login' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
    $this->assertDatabaseHas('sessions', ['user_id' => $user->id]);

    $user->forceDelete();

    $this->assertDatabaseMissing('role_user', ['user_id' => $user->id]);
    $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    $this->assertModelExists($role);
});

test('deleting a role removes only its memberships', function (): void {
    $admin = Role::factory()->create(['name' => 'admin']);
    $userRole = Role::factory()->create(['name' => 'user']);
    $user = User::factory()->hasAttached([$admin, $userRole])->create();

    $admin->delete();

    $this->assertDatabaseMissing('role_user', ['role_id' => $admin->id]);
    $this->assertDatabaseHas('role_user', ['role_id' => $userRole->id, 'user_id' => $user->id]);
    $this->assertModelExists($user);
});

test('available role options include every seeded and custom role', function (): void {
    $this->seed(RoleSeeder::class);
    Role::factory()->create(['name' => 'editor']);

    expect(User::getRoleOptions())->toBe(['admin', 'editor', 'superadmin', 'user']);
});
