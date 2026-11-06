<?php

namespace App\Services\Conversion;

class SurpacStrParserService
{
    /**
     * Parse Surpac .str file to GeoJSON (Intermediate in Source CRS)
     *
     * @param  string  $path  Absolute path to .str file
     * @return array GeoJSON FeatureCollection
     *
     * @throws \Exception
     */
    private function features(string $path): \Generator
    {
        $currentSegment = [];

        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new \Exception('Could not open file: '.$path);
        }

        try {
            while (($line = fgets($handle, 65537)) !== false) {
                if (! str_ends_with($line, "\n") && ! feof($handle)) {
                    throw new \RuntimeException('STR line exceeds the processing limit.');
                }
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                // Try comma-delimited first, fall back to whitespace-delimited
                $parts = explode(',', $line);
                if (count($parts) < 4) {
                    $parts = preg_split('/\s+/', $line);
                }
                $parts = array_map('trim', $parts);

                // Skip lines that don't have enough columns or don't start with a number
                if (count($parts) < 4 || ! is_numeric($parts[0])) {
                    // If we haven't found data yet, this is still header — just skip
                    // If we already have data, this is a comment/description line — also skip
                    continue;
                }

                // Once we reach here, we know we have a valid data line

                $stringId = intval($parts[0]);
                $y = floatval($parts[1]); // North
                $x = floatval($parts[2]); // East
                $z = floatval($parts[3]); // Elevation

                // End of segment marker (0, 0.0, 0.0, 0.0)
                if ($stringId === 0 && $y == 0 && $x == 0 && $z == 0) {
                    if (! empty($currentSegment)) {
                        yield $this->createFeature($currentSegment);
                        $currentSegment = [];
                    }

                    continue;
                }

                // Easting, Northing, Elevation for GeoJSON [X, Y, Z]
                $currentSegment[] = [$x, $y, $z];
                if (count($currentSegment) > 100000) {
                    throw new \RuntimeException('STR segment exceeds the 100,000 vertex limit.');
                }
            }

            // Catch last segment if not closed by 0,0,0,0
            if (! empty($currentSegment)) {
                yield $this->createFeature($currentSegment);
            }

        } finally {
            fclose($handle);
        }
    }

    public function parse(string $path): array
    {
        return ['type' => 'FeatureCollection', 'features' => iterator_to_array($this->features($path), false)];
    }

    public function writeGeoJson(string $path, string $outputPath): void
    {
        $output = fopen($outputPath, 'wb');
        if ($output === false) {
            throw new \RuntimeException('Unable to create STR output.');
        }
        try {
            $this->write($output, '{"type":"FeatureCollection","features":[');
            $first = true;
            foreach ($this->features($path) as $feature) {
                $this->write($output, ($first ? '' : ',').json_encode($feature, JSON_THROW_ON_ERROR));
                $first = false;
            }
            $this->write($output, ']}');
        } finally {
            fclose($output);
        }
    }

    private function write($output, string $data): void
    {
        $offset = 0;
        while ($offset < strlen($data)) {
            $written = fwrite($output, substr($data, $offset));
            if (! $written) {
                throw new \RuntimeException('Unable to write STR output.');
            }
            $offset += $written;
        }
    }

    private function createFeature(array $points): array
    {
        if (count($points) === 1) {
            return [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => $points[0],
                ],
                'properties' => [],
            ];
        }

        return [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => $points,
            ],
            'properties' => [],
        ];
    }
}
