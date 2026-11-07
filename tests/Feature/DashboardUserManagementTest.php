<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(LazilyRefreshDatabase::class);

test('guests are redirected and engineering users are forbidden', function (): void {
    $target = User::factory()->create();

    $this->get(route('dashboard.users.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create());
    $this->get(route('dashboard.users.index'))->assertForbidden();
    $this->post(route('dashboard.users.store'), [])->assertForbidden();
    $this->put(route('dashboard.users.update', $target), [])->assertForbidden();
    $this->delete(route('dashboard.users.destroy', $target))->assertForbidden();
    $this->get(route('dashboard'))->assertOk()->assertDontSee(route('dashboard.users.index'));

    $this->assertNotSoftDeleted($target);
});

test('admins see the paginated user list and the navigation entry', function (): void {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['name' => 'Eli Engineer']);
    User::factory()->count(15)->create();

    $this->actingAs($admin)->get(route('dashboard.users.index'))
        ->assertOk()
        ->assertSee('User Management')
        ->assertSee(route('dashboard.users.index'))
        ->assertViewHas('users', fn ($users): bool => $users->total() === 17 && $users->count() === 15);
});

test('search matches name, username or email and combines with the role filter', function (): void {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['name' => 'Needle In Name']);
    User::factory()->create(['name' => 'Username Match', 'username' => 'the_needle']);
    User::factory()->admin()->create(['name' => 'Email Match', 'email' => 'needle@example.com']);
    User::factory()->create(['name' => 'Unrelated Person']);

    $this->actingAs($admin)->get(route('dashboard.users.index', ['search' => 'needle']))
        ->assertOk()
        ->assertSee(['Needle In Name', 'Username Match', 'Email Match'])
        ->assertDontSee('Unrelated Person');

    $this->get(route('dashboard.users.index', ['search' => 'needle', 'role' => UserRole::Admin->value]))
        ->assertOk()
        ->assertSee('Email Match')
        ->assertDontSee(['Needle In Name', 'Username Match', 'Unrelated Person']);
});

test('admins can create a user with normalised identifiers and a hashed password', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->from(route('dashboard.users.index'))->post(route('dashboard.users.store'), [
        'name' => 'New Engineer',
        'username' => ' New.Engineer ',
        'email' => 'New@Example.com',
        'role' => UserRole::Engineering->value,
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard.users.index'))->assertSessionHas('success');

    $user = User::query()->where('username', 'new.engineer')->firstOrFail();
    expect($user->email)->toBe('new@example.com');
    expect($user->role)->toBe(UserRole::Engineering);
    expect($user->password)->not->toBe('secret-password');
    expect(Hash::check('secret-password', $user->password))->toBeTrue();
});

test('creating a user requires a password, a valid role and unique identifiers', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->from(route('dashboard.users.index'))->post(route('dashboard.users.store'), [
        'name' => 'Duplicate',
        'username' => strtoupper($admin->username),
        'email' => $admin->email,
        'role' => 'superadmin',
        'password' => '',
    ])->assertRedirect(route('dashboard.users.index'))->assertSessionHasErrors(['username', 'email', 'role', 'password']);

    $this->assertDatabaseCount('users', 1);
});

test('updating a user keeps the current password when it is left blank', function (): void {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $originalPassword = $user->password;

    $this->actingAs($admin)->from(route('dashboard.users.index'))->put(route('dashboard.users.update', $user), [
        'name' => 'Renamed Person',
        'username' => $user->username,
        'email' => $user->email,
        'role' => UserRole::Admin->value,
        'password' => '',
        'password_confirmation' => '',
    ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard.users.index'));

    $user->refresh();
    expect($user->name)->toBe('Renamed Person');
    expect($user->role)->toBe(UserRole::Admin);
    expect($user->password)->toBe($originalPassword);
});

test('updating a password hashes it and rotates the remember token', function (): void {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $rememberToken = $user->remember_token;

    $this->actingAs($admin)->put(route('dashboard.users.update', $user), [
        'name' => $user->name,
        'username' => $user->username,
        'email' => $user->email,
        'role' => UserRole::Engineering->value,
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ])->assertSessionHasNoErrors();

    $user->refresh();
    expect(Hash::check('new-secret-password', $user->password))->toBeTrue();
    expect($user->remember_token)->not->toBe($rememberToken);
});

test('admins can demote and delete another admin while one admin remains', function (): void {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();

    $this->actingAs($admin)->put(route('dashboard.users.update', $otherAdmin), [
        'name' => $otherAdmin->name,
        'username' => $otherAdmin->username,
        'email' => $otherAdmin->email,
        'role' => UserRole::Engineering->value,
    ])->assertSessionHasNoErrors();
    expect($otherAdmin->fresh()->role)->toBe(UserRole::Engineering);

    $this->delete(route('dashboard.users.destroy', $otherAdmin))->assertSessionHas('success');
    $this->assertSoftDeleted($otherAdmin);
});

test('admins cannot delete or demote their own account but can edit their details', function (): void {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create();

    $this->actingAs($admin)->from(route('dashboard.users.index'))->delete(route('dashboard.users.destroy', $admin))
        ->assertRedirect(route('dashboard.users.index'))
        ->assertSessionHas('error', 'You cannot delete your own account.');

    $this->put(route('dashboard.users.update', $admin), [
        'name' => $admin->name,
        'username' => $admin->username,
        'email' => $admin->email,
        'role' => UserRole::Engineering->value,
    ])->assertSessionHasErrors(['role' => 'You cannot remove your own admin role.']);

    $this->put(route('dashboard.users.update', $admin), [
        'name' => 'Renamed Admin',
        'username' => $admin->username,
        'email' => $admin->email,
        'role' => UserRole::Admin->value,
    ])->assertSessionHasNoErrors();

    $this->assertNotSoftDeleted($admin);
    expect($admin->fresh())->name->toBe('Renamed Admin')->role->toBe(UserRole::Admin);
});

test('the last remaining admin cannot be demoted or deleted', function (): void {
    $actor = User::factory()->admin()->create();
    $lastAdmin = User::factory()->admin()->create();

    // The acting admin is demoted by another session mid-request, leaving $lastAdmin as the only admin.
    User::query()->whereKey($actor->id)->update(['role' => UserRole::Engineering->value]);

    $this->actingAs($actor)->put(route('dashboard.users.update', $lastAdmin), [
        'name' => $lastAdmin->name,
        'username' => $lastAdmin->username,
        'email' => $lastAdmin->email,
        'role' => UserRole::Engineering->value,
    ])->assertSessionHasErrors(['role' => 'At least one admin must remain. Make another user an admin first.']);

    $this->delete(route('dashboard.users.destroy', $lastAdmin))
        ->assertSessionHas('error', 'At least one admin must remain. Make another user an admin first.');

    $this->assertNotSoftDeleted($lastAdmin);
    expect($lastAdmin->fresh()->role)->toBe(UserRole::Admin);
});
