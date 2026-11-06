<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(LazilyRefreshDatabase::class);

test('guests cannot access user management', function (): void {
    $this->get(route('users.index'))->assertRedirect(route('login'));
    $this->postJson(route('users.store'), [])->assertUnauthorized();
});

test('unprivileged roles cannot read or mutate users', function (string $roleName): void {
    $actor = User::factory()->hasAttached(Role::factory()->create(['name' => $roleName]))->create();
    $target = User::factory()->create();

    $this->actingAs($actor)->get(route('users.index'))->assertForbidden();
    $this->getJson(route('users.show', $target))->assertForbidden();
    $this->postJson(route('users.store'), [])->assertForbidden();
    $this->patchJson(route('users.update', $target), [])->assertForbidden();
    $this->deleteJson(route('users.destroy', $target))->assertForbidden();
    $this->assertNotSoftDeleted($target);
})->with(['admin', 'user']);

test('unverified superadmins cannot access user management', function (): void {
    $actor = User::factory()->unverified()->hasAttached(Role::factory()->create(['name' => 'superadmin']))->create();

    $this->actingAs($actor)->get(route('users.index'))->assertRedirect(route('verification.notice'));
    $this->postJson(route('users.store'), [])->assertForbidden();
});

test('superadmins can open the user page and get only paginated rows over ajax', function (): void {
    $actor = User::factory()->hasAttached(Role::factory()->create(['name' => 'superadmin']))->create();
    User::factory()->count(11)->create();

    $this->actingAs($actor)->get(route('users.index'))->assertOk()->assertSee('Users management')->assertSee('Filter by role');
    $response = $this->getJson(route('users.index', ['per_page' => 10, 'page' => 2]));

    $response->assertOk()->assertJsonStructure(['html']);
    expect($response->json('html'))->toContain('Showing 11–12 of 12 users')->not->toContain('<html');
});

test('search and role filters combine and exclude deleted users', function (): void {
    $actor = User::factory()->hasAttached(Role::factory()->create(['name' => 'superadmin']))->create();
    $role = Role::factory()->create(['name' => 'user']);
    $match = User::factory()->hasAttached($role)->create(['name' => 'Matched Person', 'username' => 'needle']);
    User::factory()->create(['name' => 'Wrong Role', 'username' => 'needle_other']);
    $deleted = User::factory()->hasAttached($role)->create(['name' => 'Deleted Person', 'username' => 'needle_deleted']);
    $deleted->delete();

    $response = $this->actingAs($actor)->getJson(route('users.index', ['search' => 'needle', 'role' => $role->id]));

    $response->assertOk();
    expect($response->json('html'))->toContain($match->name)->not->toContain('Wrong Role')->not->toContain('Deleted Person')->toContain('of 1 users');
});

test('allowed columns sort users in both directions on the server', function (string $column): void {
    $actor = User::factory()->hasAttached(Role::factory()->create(['name' => 'superadmin']))->create();
    $roleA = Role::factory()->create(['name' => 'aaa']);
    $roleZ = Role::factory()->create(['name' => 'zzz']);
    $a = User::factory()->hasAttached($roleA)->unverified()->create(['name' => 'AAA Person', 'username' => 'aaa', 'email' => 'aaa@example.com', 'created_at' => '2020-01-01']);
    $z = User::factory()->hasAttached($roleZ)->create(['name' => 'ZZZ Person', 'username' => 'zzz', 'email' => 'zzz@example.com', 'created_at' => '2030-01-01']);

    $ascending = $this->actingAs($actor)->getJson(route('users.index', ['sort' => $column, 'direction' => 'asc']));
    $descending = $this->getJson(route('users.index', ['sort' => $column, 'direction' => 'desc']));

    $ascending->assertOk();
    $descending->assertOk();
    expect(strpos($ascending->json('html'), $a->name))->toBeLessThan(strpos($ascending->json('html'), $z->name));
    expect(strpos($descending->json('html'), $z->name))->toBeLessThan(strpos($descending->json('html'), $a->name));
})->with(['name', 'username', 'email', 'role', 'created_at', 'email_verified_at']);

test('invalid sort and pagination inputs return validation errors', function (): void {
    $actor = User::factory()->hasAttached(Role::factory()->create(['name' => 'superadmin']))->create();

    $this->actingAs($actor)->getJson(route('users.index', ['sort' => 'password', 'direction' => 'random', 'per_page' => 999, 'page' => 0]))
        ->assertUnprocessable()->assertJsonValidationErrors(['sort', 'direction', 'per_page', 'page']);
});

test('user content is escaped in the ajax table', function (): void {
    $actor = User::factory()->hasAttached(Role::factory()->create(['name' => 'superadmin']))->create();
    $target = User::factory()->create(['name' => '<script>alert(1)</script>']);

    $response = $this->actingAs($actor)->getJson(route('users.index'));

    $response->assertOk();
    expect($response->json('html'))->toContain(e($target->name))->not->toContain($target->name);
});

test('superadmins can create verified users without accepting unrelated attributes', function (): void {
    $actor = User::factory()->hasAttached(Role::factory()->create(['name' => 'superadmin']))->create();
    $role = Role::factory()->create(['name' => 'admin']);

    $this->actingAs($actor)->postJson(route('users.store'), [
        'name' => 'New Person', 'username' => 'newperson', 'email' => 'new@example.com',
        'role_id' => $role->id, 'verified' => true, 'password' => 'secure-password', 'provider_id' => 'untrusted',
    ])->assertCreated()->assertJsonPath('message', 'User created successfully.');

    $user = User::query()->where('username', 'newperson')->firstOrFail();
    expect($user->hasVerifiedEmail())->toBeTrue();
    expect($user->hasRole('admin'))->toBeTrue();
    expect(Hash::check('secure-password', $user->password))->toBeTrue();
    expect($user->provider_id)->toBeNull();
    expect($user->profile_photo_path)->toBe(User::DEFAULT_PHOTO_PATHS[1]);
});

test('invalid and duplicate user details are rejected without creating an account', function (): void {
    $actor = User::factory()->hasAttached(Role::factory()->create(['name' => 'superadmin']))->create();

    $this->actingAs($actor)->postJson(route('users.store'), [
        'name' => '', 'username' => $actor->username, 'email' => $actor->email,
        'role_id' => 99999, 'verified' => 'maybe', 'password' => 'short',
    ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'username', 'email', 'role_id', 'verified', 'password']);
    $this->assertDatabaseCount('users', 1);
});

test('edit details exclude secrets and updating preserves a blank password', function (): void {
    $actor = User::factory()->hasAttached(Role::factory()->create(['name' => 'superadmin']))->create();
    $role = Role::factory()->create(['name' => 'user']);
    $target = User::factory()->create();
    $originalPassword = $target->password;

    $this->actingAs($actor)->getJson(route('users.show', $target))->assertOk()->assertJsonMissingPath('user.password')->assertJsonMissingPath('user.provider_token');
    $this->patchJson(route('users.update', $target), [
        'name' => 'Updated Person', 'username' => $target->username, 'email' => $target->email,
        'role_id' => $role->id, 'verified' => false, 'password' => '',
    ])->assertOk();

    $target->refresh();
    expect($target->name)->toBe('Updated Person');
    expect($target->password)->toBe($originalPassword);
    expect($target->hasVerifiedEmail())->toBeFalse();
    expect($target->roles->pluck('id')->all())->toBe([$role->id]);
});

test('updating a password hashes it and rotates the remember token', function (): void {
    $actor = User::factory()->hasAttached(Role::factory()->create(['name' => 'superadmin']))->create();
    $role = Role::factory()->create(['name' => 'user']);
    $target = User::factory()->hasAttached($role)->create();
    $token = $target->remember_token;

    $this->actingAs($actor)->patchJson(route('users.update', $target), [
        'name' => $target->name, 'username' => $target->username, 'email' => $target->email,
        'role_id' => $role->id, 'verified' => true, 'password' => 'new-secure-password',
    ])->assertOk();

    $target->refresh();
    expect(Hash::check('new-secure-password', $target->password))->toBeTrue();
    expect($target->remember_token)->not->toBe($token);
});

test('superadmins cannot delete themselves or remove their own access', function (): void {
    $actor = User::factory()->hasAttached(Role::factory()->create(['name' => 'superadmin']))->create();
    $role = Role::factory()->create(['name' => 'user']);

    $this->actingAs($actor)->deleteJson(route('users.destroy', $actor))->assertUnprocessable()->assertJsonValidationErrors('user');
    $this->patchJson(route('users.update', $actor), [
        'name' => $actor->name, 'username' => $actor->username, 'email' => $actor->email,
        'role_id' => $role->id, 'verified' => false,
    ])->assertUnprocessable()->assertJsonValidationErrors(['role_id', 'verified']);
    $this->assertNotSoftDeleted($actor);
    expect($actor->fresh()->hasRole('superadmin'))->toBeTrue();
});

test('deleting a user soft deletes the account and makes it inaccessible', function (): void {
    $actor = User::factory()->hasAttached(Role::factory()->create(['name' => 'superadmin']))->create();
    $target = User::factory()->create();

    $this->actingAs($actor)->deleteJson(route('users.destroy', $target))->assertOk();

    $this->assertSoftDeleted($target);
    $this->getJson(route('users.show', $target))->assertNotFound();
});
