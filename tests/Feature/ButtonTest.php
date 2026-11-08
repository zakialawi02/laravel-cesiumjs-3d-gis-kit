<?php

use Illuminate\Support\Facades\Blade;

/**
 * Render a Blade snippet and return the first element with the given tag.
 */
function renderButtonElement(string $template, string $tag, array $data = []): ?DOMElement
{
    $document = new DOMDocument;
    $document->loadHTML(Blade::render($template, $data), LIBXML_NOERROR | LIBXML_NOWARNING);

    return $document->getElementsByTagName($tag)->item(0);
}

test('buttons render a plain button by default', function (string $component): void {
    $button = renderButtonElement("<x-{$component}>Save</x-{$component}>", 'button');

    expect($button->getAttribute('type'))->toBe('button');
    expect($button->hasAttribute('disabled'))->toBeFalse();
    expect(trim($button->textContent))->toBe('Save');
})->with(['button-primary', 'button-light', 'button-success']);

test('buttons forward a custom form action and attributes', function (string $component): void {
    $button = renderButtonElement("<x-{$component} type=\"submit\" size=\"small\" variant=\"outline\" data-action=\"save\">Save</x-{$component}>", 'button');

    expect($button->getAttribute('type'))->toBe('submit');
    expect($button->getAttribute('data-action'))->toBe('save');
    expect($button->getAttribute('class'))->toContain('text-sm');
})->with(['button-primary', 'button-light', 'button-success']);

test('buttons can be disabled through a bound attribute', function (string $component, bool $disabled): void {
    $button = renderButtonElement("<x-{$component} type=\"submit\" :disabled=\"\$disabled\">Send</x-{$component}>", 'button', ['disabled' => $disabled]);

    expect($button->hasAttribute('disabled'))->toBe($disabled);
})->with(['button-primary', 'button-light', 'button-success'])->with([
    'disabled' => true,
    'enabled' => false,
]);

test('buttons render navigation links when given a destination', function (string $component): void {
    $document = new DOMDocument;
    $document->loadHTML(Blade::render("<x-{$component} href=\"/profile\" variant=\"outline\">Profile</x-{$component}>"), LIBXML_NOERROR | LIBXML_NOWARNING);
    $link = $document->getElementsByTagName('a')->item(0);

    expect($document->getElementsByTagName('button')->length)->toBe(0);
    expect($link->getAttribute('href'))->toBe('/profile');
    expect($link->hasAttribute('type'))->toBeFalse();
    expect(trim($link->textContent))->toBe('Profile');
})->with(['button-primary', 'button-light', 'button-success']);

test('the starter kit secondary and danger button variants are not available', function (string $component): void {
    expect(fn () => Blade::render("<x-{$component}>Save</x-{$component}>"))
        ->toThrow(InvalidArgumentException::class, "Unable to locate a class or view for component [{$component}].");
})->with(['button-secondary', 'button-danger']);
