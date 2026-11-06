<?php

use Illuminate\Support\Facades\Blade;

test('buttons use the appropriate form action by default', function (string $component, string $type): void {
    $html = Blade::render("<x-{$component}>Save</x-{$component}>");
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $button = $document->getElementsByTagName('button')->item(0);

    expect($button->getAttribute('type'))->toBe($type);
    expect($button->hasAttribute('disabled'))->toBeFalse();
    expect($button->hasAttribute('aria-busy'))->toBeFalse();
    expect(trim($button->textContent))->toBe('Save');
})->with([
    'primary' => ['button-primary', 'submit'],
    'secondary' => ['button-secondary', 'button'],
    'danger' => ['button-danger', 'submit'],
]);

test('loading buttons prevent another action and announce their busy state', function (string $component): void {
    $html = Blade::render("<x-{$component} :loading=\"true\">Processing</x-{$component}>");
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $button = $document->getElementsByTagName('button')->item(0);

    expect($button->hasAttribute('disabled'))->toBeTrue();
    expect($button->getAttribute('aria-busy'))->toBe('true');
    expect(trim($button->textContent))->toBe('Processing');
})->with(['button-primary', 'button-secondary', 'button-danger']);

test('buttons can be disabled without showing a loading state', function (string $component): void {
    $html = Blade::render("<x-{$component} :disabled=\"true\">Save</x-{$component}>");
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $button = $document->getElementsByTagName('button')->item(0);

    expect($button->hasAttribute('disabled'))->toBeTrue();
    expect($button->hasAttribute('aria-busy'))->toBeFalse();
})->with(['button-primary', 'button-secondary', 'button-danger']);

test('secondary buttons forward a custom form action and attributes', function (): void {
    $html = Blade::render('<x-button-secondary type="reset" size="small" variant="solid" data-action="clear">Reset</x-button-secondary>');
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $button = $document->getElementsByTagName('button')->item(0);

    expect($button->getAttribute('type'))->toBe('reset');
    expect($button->getAttribute('data-action'))->toBe('clear');
});

test('buttons render navigation links when given a destination', function (string $component): void {
    $html = Blade::render("<x-{$component} href=\"/profile\" variant=\"outline\">Profile</x-{$component}>");
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $link = $document->getElementsByTagName('a')->item(0);

    expect($document->getElementsByTagName('button')->length)->toBe(0);
    expect($link->getAttribute('href'))->toBe('/profile');
    expect($link->hasAttribute('type'))->toBeFalse();
    expect(trim($link->textContent))->toBe('Profile');
})->with(['button-primary', 'button-secondary', 'button-danger']);

test('disabled and loading button links cannot navigate', function (string $component, bool $loading, bool $disabled): void {
    $html = Blade::render(
        "<x-{$component} href=\"/profile\" :loading=\"\$loading\" :disabled=\"\$disabled\">Profile</x-{$component}>",
        ['loading' => $loading, 'disabled' => $disabled],
    );
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $link = $document->getElementsByTagName('a')->item(0);

    expect($link->hasAttribute('href'))->toBeFalse();
    expect($link->getAttribute('aria-disabled'))->toBe('true');
    expect($link->getAttribute('tabindex'))->toBe('-1');
    expect($link->getAttribute('aria-busy'))->toBe($loading ? 'true' : '');
})->with(['button-primary', 'button-secondary', 'button-danger'])->with([
    'loading' => [true, false],
    'disabled' => [false, true],
]);
