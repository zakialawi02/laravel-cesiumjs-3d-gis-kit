<?php

use App\Services\Conversion\DxfColorInjector;

test('aci color codes map to their hex colors', function (int $aciColor, string $hexColor): void {
    expect(DxfColorInjector::aciToHex($aciColor))->toBe($hexColor);
})->with([
    'black' => [0, '#000000'],
    'red' => [1, '#FF0000'],
    'white' => [7, '#FFFFFF'],
    'extended orange' => [30, '#FF7F00'],
    'gray ramp' => [250, '#333333'],
    'last code' => [255, '#FFFFFF'],
]);

test('codes outside the aci range have no hex color', function (int $aciColor): void {
    expect(DxfColorInjector::aciToHex($aciColor))->toBeNull();
})->with([
    'bylayer' => [256],
    'negative' => [-1],
]);
