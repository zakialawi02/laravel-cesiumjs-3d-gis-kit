<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ViewErrorBag;

uses(LazilyRefreshDatabase::class);

/**
 * Render a layout component with pushed (and optionally slotted) CSS and JavaScript.
 */
function renderLayoutWithAssets(string $component, bool $withNamedSlots): DOMXPath
{
    $slots = $withNamedSlots ? <<<'BLADE'
        <x-slot:css>
            <style data-slot-css>.slot-marker { color: blue; }</style>
        </x-slot:css>
        <x-slot:javascript>
            <script data-slot-javascript>window.slotReady = true;</script>
        </x-slot:javascript>
        BLADE : '';

    $html = Blade::render(<<<BLADE
        <x-{$component}>
            @push('css')
                <style data-stack-css>.stack-marker { color: red; }</style>
            @endpush
            {$slots}
            <p>Page content</p>
            @push('javascript')
                <script data-stack-javascript>window.stackReady = true;</script>
            @endpush
        </x-{$component}>
        BLADE);
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

    return new DOMXPath($document);
}

test('layouts render pushed css in the head and pushed javascript at the end of the body', function (string $component): void {
    $this->actingAs(User::factory()->admin()->create());
    view()->share('errors', new ViewErrorBag);

    $xpath = renderLayoutWithAssets($component, withNamedSlots: false);

    expect($xpath->query('/html/head/style[@data-stack-css]')->length)->toBe(1);
    expect($xpath->query('/html/body/script[@data-stack-javascript]')->length)->toBe(1);
})->with(['app-layout', 'guest-layout', 'app-front-layout']);

test('the guest layout also accepts css and javascript through named slots', function (): void {
    view()->share('errors', new ViewErrorBag);

    $xpath = renderLayoutWithAssets('guest-layout', withNamedSlots: true);

    expect($xpath->query('/html/head/style[@data-slot-css]')->length)->toBe(1);
    expect($xpath->query('/html/body/script[@data-slot-javascript]')->length)->toBe(1);
});

test('only the map page loads its dedicated javascript entry', function (): void {
    $mapScript = Vite::asset('resources/js/map.js');

    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('home'))->assertOk()->assertSee($mapScript, escape: false);
    $this->get(route('dashboard'))->assertOk()->assertDontSee($mapScript, escape: false);
    $this->get(route('dashboard.users.index'))->assertOk()->assertDontSee($mapScript, escape: false);
    $this->get(route('profile.edit'))->assertOk()->assertDontSee($mapScript, escape: false);
});

test('guest pages do not load the map javascript entry', function (): void {
    $mapScript = Vite::asset('resources/js/map.js');

    $this->get(route('login'))->assertOk()->assertDontSee($mapScript, escape: false);
});
