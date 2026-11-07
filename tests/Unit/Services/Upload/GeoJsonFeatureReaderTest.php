<?php

use App\Services\Upload\GeoJsonFeatureReader;

function readGeoJsonFeatures(string $contents): array
{
    return iterator_to_array((new GeoJsonFeatureReader)->features(temporaryFileWith($contents)), false);
}

test('each feature of a feature collection is yielded as an array', function (): void {
    $features = readGeoJsonFeatures(<<<'JSON'
        {
          "type": "FeatureCollection",
          "name": "survey",
          "features": [
            {"type": "Feature", "geometry": {"type": "Point", "coordinates": [106.8, -6.2]}, "properties": {"id": 1}},
            {"type": "Feature", "geometry": null}
          ]
        }
        JSON);

    expect($features)->toBe([
        ['type' => 'Feature', 'geometry' => ['type' => 'Point', 'coordinates' => [106.8, -6.2]], 'properties' => ['id' => 1]],
        ['type' => 'Feature', 'geometry' => null],
    ]);
});

test('braces, brackets and escaped quotes inside strings do not end a feature', function (): void {
    $features = readGeoJsonFeatures(
        '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":null,"properties":{"note":"a } ] \" [ {"}}]}'
    );

    expect($features[0]['properties']['note'])->toBe('a } ] " [ {');
});

test('root members after the features array are accepted', function (): void {
    $features = readGeoJsonFeatures(
        '{"features":[{"type":"Feature","geometry":null}],"type":"FeatureCollection","bbox":[0,0,1,1]}'
    );

    expect($features)->toBe([['type' => 'Feature', 'geometry' => null]]);
});

test('an empty feature collection yields no features', function (): void {
    expect(readGeoJsonFeatures('{"type":"FeatureCollection","features":[]}'))->toBe([]);
});

test('malformed documents are rejected', function (string $contents, string $message): void {
    expect(fn () => readGeoJsonFeatures($contents))->toThrow(RuntimeException::class, $message);
})->with([
    'trailing comma' => [
        '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":null},]}',
        'Trailing comma in GeoJSON features.',
    ],
    'missing comma between features' => [
        '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":null} {"type":"Feature","geometry":null}]}',
        'Invalid GeoJSON feature list.',
    ],
    'non-object list item' => [
        '{"type":"FeatureCollection","features":[1]}',
        'Invalid GeoJSON feature list.',
    ],
    'wrong feature type' => [
        '{"type":"FeatureCollection","features":[{"type":"Point","geometry":null}]}',
        'Invalid GeoJSON feature.',
    ],
    'missing geometry' => [
        '{"type":"FeatureCollection","features":[{"type":"Feature","properties":{}}]}',
        'Invalid GeoJSON feature.',
    ],
    'scalar geometry' => [
        '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":"POINT (1 2)"}]}',
        'Invalid GeoJSON geometry or properties.',
    ],
    'scalar properties' => [
        '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":null,"properties":"name"}]}',
        'Invalid GeoJSON geometry or properties.',
    ],
    'duplicate features array' => [
        '{"type":"FeatureCollection","features":[],"features":[]}',
        'Duplicate GeoJSON features array.',
    ],
    'wrong root type' => [
        '{"type":"GeometryCollection","features":[]}',
        'Expected a valid GeoJSON FeatureCollection.',
    ],
    'missing features array' => [
        '{"type":"FeatureCollection"}',
        'Expected a valid GeoJSON FeatureCollection.',
    ],
    'nested features array only' => [
        '{"type":"FeatureCollection","meta":{"features":[]}}',
        'Expected a valid GeoJSON FeatureCollection.',
    ],
]);

test('truncated documents are rejected as invalid json', function (): void {
    expect(fn () => readGeoJsonFeatures('{"type":"FeatureCollection","features":[{"type":"Feature","geometry":null}'))
        ->toThrow(JsonException::class);
});
