<?php

namespace App\Services\Upload;

class UploadFileValidator
{
    public function validate(string $path, string $extension): void
    {
        $extension = strtolower($extension);
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Unable to inspect uploaded file.');
        }
        try {
            $header = fread($handle, 65536);
        } finally {
            fclose($handle);
        }
        if ($header === false || $header === '') {
            throw new \RuntimeException('Uploaded file is empty.');
        }
        $valid = match ($extension) {
            'tif', 'tiff', 'geotiff' => in_array(substr($header, 0, 4), ["II\x2a\x00", "MM\x00\x2a", "II\x2b\x00", "MM\x00\x2b"], true),
            'zip' => str_starts_with($header, "PK\x03\x04"),
            'dwg' => preg_match('/^AC10[0-9]{2}/', $header) === 1,
            'dxf' => str_starts_with($header, "AutoCAD Binary DXF\r\n\x1a\x00") || preg_match('/\b0\s+SECTION\b/i', $header) === 1,
            'str' => ! str_contains($header, "\x00") && preg_match('/^\s*\d+\s*[,\s]\s*-?[\d.]+\s*[,\s]\s*-?[\d.]+\s*[,\s]\s*-?[\d.]+/m', $header) === 1,
            'geojson' => true,
            default => false,
        };
        if (! $valid) {
            throw new \RuntimeException('File content does not match the supported format.');
        }
        if ($extension === 'geojson') {
            foreach (app(GeoJsonFeatureReader::class)->features($path) as $feature) {
                // Exhaust the streaming parser to validate the complete document.
            }
        }
    }
}
