<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('login strips redirect targets outside the application', function (string $target): void {
    $this->get('/login?redirect='.urlencode($target))
        ->assertRedirect(route('login'));

    expect(session('url.intended'))->toBeNull();
})->with([
    'external https' => 'https://www.linkedin.com/',
    'external http' => 'http://evil.example/dashboard',
    'protocol relative' => '//evil.example/dashboard',
    'backslash trick' => '/\\evil.example',
    'scheme without host' => 'https:evil.example',
    'javascript scheme' => 'javascript:alert(1)',
    'lookalike host' => 'http://localhost:8000.evil.example/',
    'credentials in url' => 'http://user@localhost:8000/dashboard',
    'relative without slash' => 'dashboard',
    'empty value' => '',
]);

test('login keeps internal redirect targets as the intended path', function (Closure $target, string $expectedPath): void {
    $this->get('/login?redirect='.urlencode($target()))->assertOk();

    expect(session('url.intended'))->toBe($expectedPath);
})->with([
    'relative path' => [fn (): string => '/profile?tab=password', '/profile?tab=password'],
    'absolute app url' => [fn (): string => rtrim(config('app.url'), '/').'/dashboard', '/dashboard'],
]);

test('users are sent to an internal redirect target after logging in', function (): void {
    $user = User::factory()->create(['password' => 'Password-123']);

    $this->get('/login?redirect='.urlencode('/profile'))->assertOk();

    $this->post('/login', ['id_user' => $user->email, 'password' => 'Password-123'])
        ->assertRedirect('/profile');

    $this->assertAuthenticatedAs($user);
});

test('an external intended url in the session is ignored after logging in', function (): void {
    $user = User::factory()->create(['password' => 'Password-123']);

    $this->withSession(['url.intended' => 'https://www.linkedin.com/'])
        ->post('/login', ['id_user' => $user->email, 'password' => 'Password-123'])
        ->assertRedirect(route('home', absolute: false));

    $this->assertAuthenticatedAs($user);
});
