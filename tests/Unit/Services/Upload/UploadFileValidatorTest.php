<?php

use App\Services\Upload\UploadFileValidator;

test('files whose content matches their extension are accepted', function (string $extension, string $contents): void {
    $path = temporaryFileWith($contents);

    expect(fn () => (new UploadFileValidator)->validate($path, $extension))->not->toThrow(Throwable::class);
})->with([
    'little-endian tiff' => ['tif', "II\x2a\x00rest"],
    'big-endian tiff' => ['tiff', "MM\x00\x2arest"],
    'little-endian bigtiff' => ['geotiff', "II\x2b\x00rest"],
    'big-endian bigtiff' => ['tif', "MM\x00\x2brest"],
    'uppercase extension' => ['TIF', "II\x2a\x00rest"],
    'zip archive' => ['zip', "PK\x03\x04rest"],
    'dwg drawing' => ['dwg', 'AC1032rest'],
    'ascii dxf' => ['dxf', "  0\nSECTION\n  2\nHEADER\n"],
    'binary dxf' => ['dxf', "AutoCAD Binary DXF\r\n\x1a\x00rest"],
    'comma delimited str' => ['str', "pit, 1-Jan-26,,\n1, 100.5, 200.25, 10.0\n"],
    'whitespace delimited str' => ['str', "1 100.5 -200.25 10.0\n"],
    'geojson collection' => ['geojson', '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":null}]}'],
]);

test('files whose content does not match their extension are rejected', function (string $extension, string $contents): void {
    $path = temporaryFileWith($contents);

    expect(fn () => (new UploadFileValidator)->validate($path, $extension))
        ->toThrow(RuntimeException::class, 'File content does not match the supported format.');
})->with([
    'png renamed to tif' => ['tif', "\x89PNG\r\n\x1a\n"],
    'text renamed to zip' => ['zip', 'not an archive'],
    'invalid dwg version' => ['dwg', 'AC10XXrest'],
    'dwg signature not at start' => ['dwg', 'xAC1032'],
    'dxf without sections' => ['dxf', "999\ncomment only\n"],
    'binary str' => ['str', "1, 100.5, 200.25, 10.0\x00"],
    'str without coordinates' => ['str', "pit, 1-Jan-26,,\nheader only\n"],
    'unsupported extension' => ['exe', 'MZ'],
]);

test('an empty file is rejected', function (): void {
    $path = temporaryFileWith('');

    expect(fn () => (new UploadFileValidator)->validate($path, 'zip'))
        ->toThrow(RuntimeException::class, 'Uploaded file is empty.');
});

test('geojson files are validated through the whole document', function (): void {
    $path = temporaryFileWith('{"type":"FeatureCollection","features":[{"type":"Feature","geometry":null},{"type":"Point"}]}');

    expect(fn () => (new UploadFileValidator)->validate($path, 'geojson'))
        ->toThrow(RuntimeException::class, 'Invalid GeoJSON feature.');
});
