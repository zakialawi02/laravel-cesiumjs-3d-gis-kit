<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(LazilyRefreshDatabase::class);

test('the starter kit user routes are not registered', function (): void {
    foreach (['users.index', 'users.show', 'users.store', 'users.update', 'users.destroy'] as $routeName) {
        expect(Route::has($routeName))->toBeFalse();
    }

    $this->actingAs(User::factory()->admin()->create())->get('/users')->assertNotFound();
});

test('guests cannot access user management', function (): void {
    $target = User::factory()->create();

    $this->get(route('dashboard.users.index'))->assertRedirect(route('login'));
    $this->post(route('dashboard.users.store'), [])->assertRedirect(route('login'));
    $this->put(route('dashboard.users.update', $target), [])->assertRedirect(route('login'));
    $this->delete(route('dashboard.users.destroy', $target))->assertRedirect(route('login'));
    $this->postJson(route('dashboard.users.store'), [])->assertUnauthorized();

    $this->assertNotSoftDeleted($target);
});

test('admins with an unverified email can still manage users because email verification is not used', function (): void {
    $admin = User::factory()->admin()->unverified()->create();

    $this->actingAs($admin)->get(route('dashboard.users.index'))->assertOk()->assertSee('User Management');
});

test('search and role filters combine and exclude deleted users', function (): void {
    $admin = User::factory()->admin()->create();
    $match = User::factory()->create(['name' => 'Matched Person', 'username' => 'needle']);
    User::factory()->admin()->create(['name' => 'Wrong Role', 'username' => 'needle_other']);
    User::factory()->create(['name' => 'Deleted Person', 'username' => 'needle_deleted'])->delete();

    $this->actingAs($admin)->get(route('dashboard.users.index', ['search' => 'needle', 'role' => UserRole::Engineering->value]))
        ->assertOk()
        ->assertSee($match->name)
        ->assertDontSee(['Wrong Role', 'Deleted Person'])
        ->assertViewHas('users', fn ($users): bool => $users->total() === 1);
});

test('unknown role filters are ignored and users stay ordered by name', function (): void {
    $admin = User::factory()->admin()->create(['name' => 'MMM Admin']);
    User::factory()->create(['name' => 'ZZZ Person']);
    User::factory()->create(['name' => 'AAA Person']);

    $this->actingAs($admin)->get(route('dashboard.users.index', ['role' => 'superadmin', 'sort' => 'name', 'direction' => 'desc']))
        ->assertOk()
        ->assertSeeInOrder(['AAA Person', 'MMM Admin', 'ZZZ Person'])
        ->assertViewHas('filters', fn (array $filters): bool => $filters['role'] === null);
});

test('user content is escaped in the table', function (): void {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create(['name' => '<script>alert(1)</script>']);

    $this->actingAs($admin)->get(route('dashboard.users.index'))
        ->assertOk()
        ->assertSee(e($target->name), escape: false)
        ->assertDontSee($target->name, escape: false);
});

test('invalid user details are rejected without creating an account', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->from(route('dashboard.users.index'))->post(route('dashboard.users.store'), [
        'name' => '',
        'username' => 'new_person',
        'email' => 'not-an-email',
        'role' => UserRole::Engineering->value,
        'password' => 'short',
        'password_confirmation' => 'different',
    ])->assertRedirect(route('dashboard.users.index'))->assertSessionHasErrors(['name', 'email', 'password']);

    $this->assertDatabaseCount('users', 1);
});

test('deleting a user soft deletes the account and makes it inaccessible', function (): void {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create(['name' => 'Soon Deleted']);

    $this->actingAs($admin)->delete(route('dashboard.users.destroy', $target))->assertSessionHas('success');

    $this->assertSoftDeleted($target);
    $this->get(route('dashboard.users.index'))->assertOk()->assertViewHas('users', fn ($users): bool => ! $users->contains($target));
    $this->put(route('dashboard.users.update', $target), [
        'name' => 'Restored Name',
        'username' => $target->username,
        'email' => $target->email,
        'role' => UserRole::Engineering->value,
    ])->assertNotFound();
    $this->delete(route('dashboard.users.destroy', $target))->assertNotFound();
});
