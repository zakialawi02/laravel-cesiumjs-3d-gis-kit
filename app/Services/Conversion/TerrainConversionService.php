<?php

namespace App\Services\Conversion;

use App\Models\SourceFile;
use Exception;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class TerrainConversionService
{
    private const GEOTIFF_PREFIX = 'geotiff/';

    private const DOCKER_SERVICE_GDAL = 'gdal';

    private const DOCKER_SERVICE_CTB = 'ctb';

    private string $dockerComposeBin;

    private string $dockerComposeFile;

    private string $basePath;

    public ?string $jobId = null;

    public function __construct()
    {
        $this->dockerComposeBin = config('geodata.docker_compose_bin', 'docker compose');
        $this->dockerComposeFile = config('geodata.docker_compose_file', 'docker-compose.tools.yml');
        $this->basePath = base_path();
    }

    public function setJobId(string $jobId): self
    {
        $this->jobId = $jobId;

        return $this;
    }

    /**
     * Validate GeoTIFF header using gdalinfo inside Docker.
     */
    public function validate(SourceFile $file): bool
    {
        $inputName = $this->extractFileName($file->storage_path);

        $this->logInfo("[VALIDATE] Checking file: {$file->storage_path}");

        $result = $this->runDockerCommand(self::DOCKER_SERVICE_GDAL, ['gdalinfo', "/data/geodata/geotiff/{$inputName}"]);

        if ($result->successful()) {
            $this->logInfo('[VALIDATE] Success: File is a valid GeoTIFF.');

            return true;
        }

        $this->logError("[VALIDATE] Failed for {$file->storage_path}: ".$result->errorOutput());

        return false;
    }

    /**
     * Reproject GeoTIFF to EPSG:4326 (WGS84) using gdalwarp.
     */
    public function reproject(SourceFile $file): string
    {
        $inputName = $this->extractFileName($file->storage_path);
        $outputName = $inputName.'_wgs84.tif';

        $this->logInfo("[REPROJECT] Reprojecting {$inputName} to WGS84");

        $sourceFileToReproject = "/data/geodata/geotiff/{$inputName}";
        $tempExpandedName = null;

        try {
            // Check for color table and expand if necessary
            $colorTableType = $this->getColorTableType($file->storage_path);
            if ($colorTableType !== null) {
                $tempExpandedName = $inputName.'_expanded.tif';
                $this->logInfo("[EXPAND] Color table (palette) detected. Expanding to {$colorTableType}...");

                $expandResult = $this->runDockerCommand(
                    self::DOCKER_SERVICE_GDAL,
                    ['gdal_translate', '-expand', $colorTableType, '-co', 'COMPRESS=LZW', '-co', 'TILED=YES', '-co', 'BIGTIFF=YES', "/data/geodata/geotiff/{$inputName}", "/data/geodata/geotiff/{$tempExpandedName}"]
                );

                if ($expandResult->successful()) {
                    $sourceFileToReproject = "/data/geodata/geotiff/{$tempExpandedName}";
                    $this->logInfo('[EXPAND] Expansion successful.');
                } else {
                    $this->logError('[EXPAND] Failed to expand color table: '.$expandResult->errorOutput());
                    throw new Exception('Failed to expand color table: '.$expandResult->errorOutput());
                }
            }

            $this->logInfo("[REPROJECT] Source file path: {$sourceFileToReproject}");

            $result = $this->runDockerCommand(
                self::DOCKER_SERVICE_GDAL,
                ['gdalwarp', '-t_srs', 'EPSG:4326', '-r', 'bilinear', '-multi', '-wo', 'NUM_THREADS=ALL_CPUS', '--config', 'GDAL_CACHEMAX', '1024', '-wm', '1024', '-co', 'COMPRESS=LZW', '-co', 'TILED=YES', '-co', 'BIGTIFF=YES', $sourceFileToReproject, "/data/geodata/geotiff/{$outputName}"]
            );
        } finally {
            // Cleanup temporary expanded file if it was created
            if ($tempExpandedName) {
                $tempRelativePath = 'geotiff/'.$tempExpandedName;
                if (Storage::disk('geodata')->exists($tempRelativePath)) {
                    Storage::disk('geodata')->delete($tempRelativePath);
                    $this->logInfo("[CLEANUP] Deleted temporary expanded file: {$tempExpandedName}");
                }
            }
        }

        if (! $result->successful()) {
            $this->logError('[REPROJECT] Failed: '.$result->errorOutput());
            throw new Exception('Reprojection failed: '.$result->errorOutput());
        }

        $this->logInfo("[REPROJECT] Success: Created WGS84 file at {$outputName}");

        return self::GEOTIFF_PREFIX.$outputName;
    }

    /**
     * Generate terrain tiles using CTB (Cesium Terrain Builder).
     */
    public function generateTerrain(string $inputPath, string $tilesetId): void
    {
        $inputName = $this->extractFileName($inputPath);
        $outputDir = "/data/terrain-tiles/{$tilesetId}";

        $this->ensureTilesetDirectory('terrain-tiles', $tilesetId);

        $this->logInfo("[CTB-TILE] Generating terrain tiles for tileset #{$tilesetId}");

        $result = $this->runDockerCommand(
            self::DOCKER_SERVICE_CTB,
            ['ctb-tile', '-f', 'Mesh', '-C', '-N', "/data/geodata/geotiff/{$inputName}", '-o', $outputDir]
        );

        if (! $result->successful()) {
            $this->logError('[CTB-TILE] Failed: '.$result->errorOutput());
            throw new Exception('Terrain generation failed: '.$result->errorOutput());
        }

        $this->logInfo("[CTB-TILE] Success: Tiles generated in {$outputDir}");
    }

    /**
     * Generate XYZ ortho tiles using gdal2tiles.py.
     */
    public function generateOrthoTiles(string $inputPath, string $layerId): string
    {
        $inputName = $this->extractFileName($inputPath);
        $tilesetId = "{$layerId}_ortho";
        $outputDir = "/data/xyz-tiles/{$tilesetId}";

        $this->ensureTilesetDirectory('xyz-tiles', $tilesetId);

        $this->logInfo("[GDAL2TILES] Generating XYZ ortho tiles for layer #{$layerId}");

        $result = $this->runDockerCommand(
            self::DOCKER_SERVICE_GDAL,
            ['gdal2tiles.py', '-r', 'lanczos', '-z', '10-18', '-w', 'none', '-p', 'mercator', '--xyz', '--processes=4', "/data/geodata/geotiff/{$inputName}", $outputDir]
        );

        if (! $result->successful()) {
            $this->logError('[GDAL2TILES] Failed: '.$result->errorOutput());
            throw new Exception('Ortho tiles generation failed: '.$result->errorOutput());
        }

        $this->logInfo("[GDAL2TILES] Success: Tiles generated in {$outputDir}");

        return "xyz-tiles/{$tilesetId}";
    }

    /**
     * Generate layer.json for the tileset.
     */
    public function generateLayerJson(string $inputPath, string $tilesetId): void
    {
        $inputName = $this->extractFileName($inputPath);
        $outputDir = "/data/terrain-tiles/{$tilesetId}";

        $this->logInfo("[CTB-LAYER] Generating layer.json for tileset #{$tilesetId}");

        $result = $this->runDockerCommand(
            self::DOCKER_SERVICE_CTB,
            ['ctb-tile', '-f', 'Mesh', '-C', '-N', '-l', "/data/geodata/geotiff/{$inputName}", '-o', $outputDir]
        );

        if (! $result->successful()) {
            $this->logError('[CTB-LAYER] Failed: '.$result->errorOutput());
            throw new Exception('Layer.json generation failed: '.$result->errorOutput());
        }

        $this->logInfo("[CTB-LAYER] Success: layer.json generated in {$outputDir}");
    }

    /**
     * Extract bounding box from GeoTIFF using gdalinfo.
     */
    public function extractBoundingBox(string $filePath): array
    {
        $fileName = $this->extractFileName($filePath);

        $this->logInfo("[METADATA] Extracting bounding box from {$fileName}");

        $result = $this->runDockerCommand(
            self::DOCKER_SERVICE_GDAL,
            ['gdalinfo', '-json', "/data/geodata/geotiff/{$fileName}"]
        );

        if (! $result->successful()) {
            $this->logError('[METADATA] Failed: '.$result->errorOutput());
            throw new Exception('Failed to extract metadata: '.$result->errorOutput());
        }

        $metadata = json_decode($result->output(), true);

        if (! isset($metadata['cornerCoordinates'])) {
            throw new Exception('Metadata does not contain cornerCoordinates');
        }

        return $this->calculateBoundingBox($metadata['cornerCoordinates']);
    }

    /**
     * Extract SRS (Coordinate Reference System) from GeoTIFF using gdalinfo.
     */
    public function extractSrs(string $filePath): ?string
    {
        $fileName = $this->extractFileName($filePath);

        $this->logInfo("[METADATA] Extracting SRS from {$fileName}");

        $result = $this->runDockerCommand(
            self::DOCKER_SERVICE_GDAL,
            ['gdalinfo', '-json', "/data/geodata/geotiff/{$fileName}"]
        );

        if ($result->successful()) {
            $metadata = json_decode($result->output(), true);

            if (isset($metadata['coordinateSystem'])) {
                $cs = $metadata['coordinateSystem'];

                // 1. Try to get EPSG from ID field (GDAL 3.x+)
                if (isset($cs['id']['authority']) && isset($cs['id']['code'])) {
                    return $cs['id']['authority'].':'.$cs['id']['code'];
                }

                // 2. Try to extract EPSG from WKT string
                if (isset($cs['wkt']) && preg_match('/ID\["EPSG",(\d+)\]/i', $cs['wkt'], $matches)) {
                    return 'EPSG:'.$matches[1];
                }

                // 3. Fallback to Name or WKT snippet (truncated)
                return $cs['name'] ?? (isset($cs['wkt']) ? substr($cs['wkt'], 0, 50) : null);
            }
        }

        return null;
    }

    /**
     * Detect if a GeoTIFF has a color table and return its type.
     * Always returns 'rgba' to ensure maximum compatibility with gdal2tiles.py
     * Returns null if no color table is found.
     */
    public function getColorTableType(string $storagePath): ?string
    {
        $inputName = $this->extractFileName($storagePath);

        $result = $this->runDockerCommand(
            self::DOCKER_SERVICE_GDAL,
            ['gdalinfo', '-json', "/data/geodata/geotiff/{$inputName}"]
        );

        if ($result->successful()) {
            $metadata = json_decode($result->output(), true);
            if (is_array($metadata) && isset($metadata['bands']) && is_array($metadata['bands'])) {
                foreach ($metadata['bands'] as $band) {
                    if (isset($band['colorTable']) || (isset($band['colorInterpretation']) && strtolower($band['colorInterpretation']) === 'palette')) {
                        // Selalu gunakan RGBA untuk web tiles agar kompatibel dan transparansi terjaga
                        return 'rgba';
                    }
                }
            }
        }

        return null;
    }

    /**
     * Extract file name from storage path by removing the geotiff/ prefix.
     */
    private function extractFileName(string $storagePath): string
    {
        return str_replace(self::GEOTIFF_PREFIX, '', $storagePath);
    }

    /**
     * Build and run docker compose command string.
     *
     * @return ProcessResult
     */
    private function runDockerCommand(string $service, array $toolCommand)
    {
        $dockerComposeArgs = explode(' ', $this->dockerComposeBin);

        $command = array_merge($dockerComposeArgs, [
            '-f',
            $this->dockerComposeFile,
            'run',
            '--rm',
        ]);

        if ($this->jobId) {
            $command[] = '-l';
            $command[] = "conversion_job_id={$this->jobId}";
        }

        $command[] = $service;
        $command = array_merge($command, $toolCommand);

        return Process::path($this->basePath)->timeout(7200)->run($command);
    }

    /**
     * Ensure the tileset directory exists in local storage for volume mapping.
     */
    private function ensureTilesetDirectory(string $baseDir, string $tilesetId): void
    {
        $fullPath = storage_path("app/public/{$baseDir}/{$tilesetId}");

        if (! File::exists($fullPath)) {
            if (! File::makeDirectory($fullPath, 0775, true, true)) {
                Log::channel('terrain_conversion')->error("[DIRECTORY] Failed to create: {$fullPath}");
                throw new Exception("Could not create output directory: {$fullPath}");
            }
        }

        $this->logInfo("[DIRECTORY] Verified: {$fullPath}");
    }

    /**
     * Calculate bounding box from corner coordinates.
     */
    private function calculateBoundingBox(array $corners): array
    {
        return [
            'west' => min($corners['upperLeft'][0], $corners['lowerLeft'][0]),
            'south' => min($corners['lowerLeft'][1], $corners['lowerRight'][1]),
            'east' => max($corners['upperRight'][0], $corners['lowerRight'][0]),
            'north' => max($corners['upperLeft'][1], $corners['upperRight'][1]),
        ];
    }

    private function logInfo(string $message): void
    {
        Log::channel('terrain_conversion')->info($message);
    }

    private function logError(string $message): void
    {
        Log::channel('terrain_conversion')->error($message);
    }
}
