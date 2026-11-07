<?php

use App\Services\Conversion\SurpacStrParserService;

test('string records become line features in easting, northing, elevation order', function (string $contents): void {
    $path = temporaryFileWith($contents);

    $collection = (new SurpacStrParserService)->parse($path);

    expect($collection)->toBe([
        'type' => 'FeatureCollection',
        'features' => [[
            'type' => 'Feature',
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => [[200.25, 100.5, 10.0], [210.0, 110.0, 12.5]],
            ],
            'properties' => [],
        ]],
    ]);
})->with([
    'comma delimited' => ["1, 100.5, 200.25, 10.0\n1, 110.0, 210.0, 12.5\n"],
    'whitespace delimited' => ["1   100.5   200.25   10.0\n1\t110.0\t210.0\t12.5\n"],
]);

test('header, axis and blank lines are skipped and segment terminators split features', function (): void {
    $path = temporaryFileWith(implode("\n", [
        'pit,  1-Jan-26,,ssi_styles:styles.ssi',
        '0,           0.000,           0.000,           0.000,           0.000,           0.000,           0.000',
        '',
        '5, 1000.0, 2000.0, 50.0, description',
        '5, 1001.0, 2001.0, 51.0',
        '0, 0.0, 0.0, 0.0',
        '7, 3000.0, 4000.0, 60.0',
        '0, 0.0, 0.0, 0.0',
        '0, 0.0, 0.0, 0.0, END',
    ]));

    $features = (new SurpacStrParserService)->parse($path)['features'];

    expect($features)->toBe([
        [
            'type' => 'Feature',
            'geometry' => ['type' => 'LineString', 'coordinates' => [[2000.0, 1000.0, 50.0], [2001.0, 1001.0, 51.0]]],
            'properties' => [],
        ],
        [
            'type' => 'Feature',
            'geometry' => ['type' => 'Point', 'coordinates' => [4000.0, 3000.0, 60.0]],
            'properties' => [],
        ],
    ]);
});

test('an unterminated final segment is still emitted', function (): void {
    $path = temporaryFileWith("1, 10.0, 20.0, 30.0\n1, 11.0, 21.0, 31.0");

    $features = (new SurpacStrParserService)->parse($path)['features'];

    expect($features)->toHaveCount(1)
        ->and($features[0]['geometry']['coordinates'])->toBe([[20.0, 10.0, 30.0], [21.0, 11.0, 31.0]]);
});

test('a file without data records produces an empty collection', function (): void {
    $path = temporaryFileWith("pit,  1-Jan-26,,ssi_styles:styles.ssi\n0, 0.0, 0.0, 0.0\n");

    $collection = (new SurpacStrParserService)->parse($path);

    expect($collection)->toBe(['type' => 'FeatureCollection', 'features' => []]);
});

test('geojson output file contains the parsed features', function (): void {
    $path = temporaryFileWith("1, 100.5, 200.25, 10.0\n1, 110.0, 210.0, 12.5\n0, 0.0, 0.0, 0.0\n2, 1.0, 2.0, 3.0\n");
    $outputPath = temporaryFileWith('');

    (new SurpacStrParserService)->writeGeoJson($path, $outputPath);

    expect(file_get_contents($outputPath))->toBe(
        '{"type":"FeatureCollection","features":['
        .'{"type":"Feature","geometry":{"type":"LineString","coordinates":[[200.25,100.5,10],[210,110,12.5]]},"properties":[]},'
        .'{"type":"Feature","geometry":{"type":"Point","coordinates":[2,1,3]},"properties":[]}'
        .']}'
    );
});

test('geojson output for a file without data records is an empty collection', function (): void {
    $path = temporaryFileWith("header line\n");
    $outputPath = temporaryFileWith('');

    (new SurpacStrParserService)->writeGeoJson($path, $outputPath);

    expect(file_get_contents($outputPath))->toBe('{"type":"FeatureCollection","features":[]}');
});

test('a line longer than the processing limit is rejected', function (): void {
    $path = temporaryFileWith('1, 1.0, 2.0, 3.0, '.str_repeat('x', 70000)."\n1, 1.0, 2.0, 3.0\n");

    expect(fn () => (new SurpacStrParserService)->parse($path))
        ->toThrow(RuntimeException::class, 'STR line exceeds the processing limit.');
});

test('a segment with more than 100000 vertices is rejected', function (): void {
    $path = temporaryFileWith(str_repeat("1, 1.0, 2.0, 3.0\n", 100001));

    expect(fn () => (new SurpacStrParserService)->parse($path))
        ->toThrow(RuntimeException::class, 'STR segment exceeds the 100,000 vertex limit.');
});
