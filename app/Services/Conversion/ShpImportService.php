<?php

namespace App\Services\Conversion;

use App\Services\Upload\ShapefileArchive;
use Illuminate\Support\Facades\File;

class ShpImportService
{
    public function __construct(protected GdalService $gdalService) {}

    /**
     * Process SHP ZIP to WGS84 GeoJSON.
     *
     * @param  string  $zipPath  Absolute path to .zip file
     * @param  string  $outputPath  Absolute path to output .geojson file
     *
     * @throws \Exception
     */
    public function process(string $zipPath, string $outputPath): bool
    {
        $extractPath = storage_path('app/geodata/temp/shp_extract_'.uniqid());
        try {
            $shpPath = app(ShapefileArchive::class)->extract($zipPath, $extractPath);
            // ogr2ogr auto-detects source CRS from .prj if present
            $this->gdalService->reprojectToWgs84($shpPath, $outputPath);
        } finally {
            // Cleanup extraction dir
            File::deleteDirectory($extractPath);
        }

        return true;
    }
}
