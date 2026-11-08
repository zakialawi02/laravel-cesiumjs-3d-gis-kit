<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('guests are sent to the login page from the map', function () {
    $this->get('/')->assertRedirect(route('login'));
});

test('the map returns a successful response for signed in users', function () {
    $this->actingAs(User::factory()->create())->get('/')->assertOk();
});
