<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile information can be updated without resetting email verification', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'username' => 'test_user',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test_user', $user->username);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNotNull($user->email_verified_at);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'username' => 'test_user',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('accounts cannot be deleted from the profile page', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ])
        ->assertMethodNotAllowed();

    $this->assertAuthenticatedAs($user);
    $this->assertNotSoftDeleted($user);
});

test('admins cannot delete their own account from the profile page either', function () {
    $admin = User::factory()->admin()->create();

    $this
        ->actingAs($admin)
        ->from('/profile')
        ->delete('/profile', [
            'password' => 'wrong-password',
        ])
        ->assertMethodNotAllowed();

    $this->assertNotSoftDeleted($admin);
});

test('profile updates reject usernames with unsupported characters', function (string $username): void {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/profile', [
        'name' => $user->name,
        'username' => $username,
        'email' => $user->email,
    ])->assertSessionHasErrors(['username' => 'Username may only contain lowercase letters, numbers, dots, dashes and underscores.']);

    $this->assertDatabaseHas('users', ['id' => $user->id, 'username' => $user->username]);
})->with(['user name', 'user@name', 'penggunaé']);

test('profile updates accept usernames with dots, dashes and underscores', function (string $username): void {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/profile', [
        'name' => $user->name,
        'username' => $username,
        'email' => $user->email,
    ])->assertSessionHasNoErrors()->assertRedirect('/profile');

    $this->assertDatabaseHas('users', ['id' => $user->id, 'username' => $username]);
})->with(['user-name', '-username', 'user.name_2']);
