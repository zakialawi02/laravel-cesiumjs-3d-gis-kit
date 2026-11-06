<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ViewErrorBag;

uses(LazilyRefreshDatabase::class);

test('layouts render css and javascript from stacks and named slots in the correct locations', function (string $component): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    view()->share('errors', new ViewErrorBag);

    $html = Blade::render(<<<BLADE
        <x-{$component}>
            @push('css')
                <style data-stack-css>.stack-marker { color: red; }</style>
            @endpush
            <x-slot:css>
                <style data-slot-css>.slot-marker { color: blue; }</style>
            </x-slot:css>
            <p>Page content</p>
            @push('javascript')
                <script data-stack-javascript>window.stackReady = true;</script>
            @endpush
            <x-slot:javascript>
                <script data-slot-javascript>window.slotReady = true;</script>
            </x-slot:javascript>
        </x-{$component}>
        BLADE);
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);

    expect($xpath->query('/html/head/style[@data-stack-css]')->length)->toBe(1);
    expect($xpath->query('/html/head/style[@data-slot-css]')->length)->toBe(1);
    expect($xpath->query('/html/body/script[@data-stack-javascript]')->length)->toBe(1);
    expect($xpath->query('/html/body/script[@data-slot-javascript]')->length)->toBe(1);
})->with(['app-layout', 'guest-layout']);

test('only the users page loads its dedicated javascript entry', function (): void {
    $actor = User::factory()->hasAttached(Role::factory()->create(['name' => 'superadmin']))->create();
    $usersScript = Vite::asset('resources/js/users.js');

    $this->actingAs($actor)->get(route('users.index'))->assertOk()->assertSee($usersScript, escape: false);
    $this->getJson(route('users.index'))->assertOk()->assertDontSee($usersScript, escape: false);
    $this->get(route('dashboard'))->assertOk()->assertDontSee($usersScript, escape: false);
    $this->get(route('profile.edit'))->assertOk()->assertDontSee($usersScript, escape: false);
});

test('guest pages do not load the users javascript entry', function (): void {
    $usersScript = Vite::asset('resources/js/users.js');

    $this->get(route('login'))->assertOk()->assertDontSee($usersScript, escape: false);
});
