<?php

namespace App\Jobs;

use App\Enums\JobStatus;
use App\Enums\LayerStatus;
use App\Enums\LayerType;
use App\Enums\SourceFileStatus;
use App\Enums\TerrainOutputType;
use App\Models\ConversionLog;
use App\Models\SourceFile;
use App\Models\TerrainDetail;
use App\Services\Conversion\TerrainConversionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessGeotiffTerrain extends BaseConversionJob
{
    public int $timeout = 7200;

    protected ?TerrainDetail $terrainDetail = null;

    protected function getLogChannel(): string
    {
        return 'terrain_conversion';
    }

    protected function getTotalSteps(): int
    {
        return $this->conversionJob->ortho_file_id !== null ? 7 : 6;
    }

    /**
     * Execute the job.
     */
    public function handle(
        TerrainConversionService $service
    ): void {
        $service->setJobId($this->conversionJob->id);

        try {
            $this->initializePipeline();

            $this->logStep(1, 'Validating GeoTIFF header...');
            $this->validateFile($service);
            $this->logStepSuccess(1, 'Validation');

            $wgs84Path = $this->reprojectToWgs84($service);
            $this->processTerrainTiles($service, $wgs84Path);
            $this->generateLayerMetadata($service, $wgs84Path);
            $bbox = $this->extractBoundingBox($service, $wgs84Path);

            $this->processOrthophoto($service);
            $this->generateOrthoXYZTiles($service);

            $this->finalizeJob($bbox);
            $this->cleanupTemporaryFile($wgs84Path);
            $this->completePipeline();
        } catch (Throwable $e) {
            $this->handleFailure($e);
            throw $e;
        }
    }

    private function initializePipeline(): void
    {
        $tool = match ($this->conversionJob->layer_type) {
            LayerType::TERRAIN => 'ctb/gdal2tiles',
            LayerType::IMAGERY => 'gdal-imagery',
            LayerType::VECTOR => 'gdal/ogr2ogr',
        };

        $this->conversionJob->update([
            'status' => JobStatus::PROCESSING,
            'conversion_tool' => $tool,
            'started_at' => now(),
        ]);

        $this->sourceFile = $this->conversionJob->sourceFile;

        // Guard: verify source file readiness
        if ($this->sourceFile->status !== SourceFileStatus::READY) {
            throw new \Exception("Source file is not ready for conversion (status: {$this->conversionJob->sourceFile->status->value})");
        }

        // Retrieve Layer record created by controller
        $this->layer = $this->conversionJob->layer;

        if (! $this->layer) {
            throw new \Exception("Layer record not found for Job #{$this->conversionJob->id}");
        }

        // Create or update TerrainDetail record
        $this->terrainDetail = TerrainDetail::updateOrCreate(
            ['layer_id' => $this->layer->id],
            [
                'source_file_id' => $this->sourceFile->id,
                'ortho_file_id' => $this->conversionJob->ortho_file_id,
                'has_ortho' => $this->conversionJob->ortho_file_id !== null,
                'output_type' => TerrainOutputType::TERRAIN,
                'acquisition_date' => $this->acquisitionDate,
            ]
        );

        // Create log entry
        $this->logEntry = ConversionLog::create([
            'job_id' => $this->conversionJob->id,
            'attempt' => $this->attempts(),
            'started_at' => now(),
        ]);

        Log::channel($this->getLogChannel())->info("Starting pipeline for Job #{$this->conversionJob->id} (File: {$this->sourceFile->original_name} - ID: {$this->sourceFile->id})");
    }

    private function validateFile(TerrainConversionService $service): void
    {
        if (! $service->validate($this->sourceFile)) {
            throw new \Exception('Invalid GeoTIFF file header/format.');
        }
    }

    private function reprojectToWgs84(TerrainConversionService $service): string
    {
        $this->logStep(2, 'Reprojecting to EPSG:4326 (WGS84)...');

        // Extract original SRS before reprojection
        $originalSrs = $service->extractSrs($this->sourceFile->storage_path);
        $this->terrainDetail->update(['srs' => $originalSrs]);

        $wgs84Path = $service->reproject($this->sourceFile);
        $this->logStepSuccess(2, 'Reprojection', "Path: {$wgs84Path}");

        return $wgs84Path;
    }

    private function processTerrainTiles(TerrainConversionService $service, string $wgs84Path): void
    {
        $this->logStep(3, 'Generating quantized-mesh terrain tiles...');
        $service->generateTerrain($wgs84Path, (string) $this->layer->id);
        $this->logStepSuccess(3, 'Tiling');
    }

    private function generateLayerMetadata(TerrainConversionService $service, string $wgs84Path): void
    {
        $this->logStep(4, 'Generating layer.json metadata...');
        $service->generateLayerJson($wgs84Path, (string) $this->layer->id);
        $this->logStepSuccess(4, 'layer.json');
    }

    private function extractBoundingBox(TerrainConversionService $service, string $wgs84Path): array
    {
        $this->logStep(5, 'Extracting bounding box for Cesium camera...');
        $bbox = $service->extractBoundingBox($wgs84Path);

        return $bbox;
    }

    private function processOrthophoto(TerrainConversionService $service): void
    {
        if (! $this->terrainDetail->has_ortho) {
            return;
        }

        $orthoFile = $this->conversionJob->orthoFile;
        if (! $orthoFile instanceof SourceFile) {
            return;
        }

        $this->logStepExtra('Reprojecting Orthophoto...');

        $this->layer->update(['status' => LayerStatus::PROCESSING]);

        $originalOrthoPath = $orthoFile->storage_path;
        $orthoWgs84 = $service->reproject($orthoFile);

        if ($originalOrthoPath && $originalOrthoPath !== $orthoWgs84) {
            Storage::disk('geodata')->delete($originalOrthoPath);
            Log::channel($this->getLogChannel())->info("[STEP EXTRA] Deleted original ortho file: {$originalOrthoPath}");
        }

        $newOrthoSize = Storage::disk('geodata')->exists($orthoWgs84) ? Storage::disk('geodata')->size($orthoWgs84) : $orthoFile->file_size;

        $orthoFile->update([
            'storage_path' => $orthoWgs84,
            'stored_name' => basename($orthoWgs84),
            'file_size' => $newOrthoSize,
        ]);

        $this->logStepSuccess(-1, 'Orthophoto Reprojection', "Layer: {$this->layerName} (Ortho size: {$newOrthoSize} bytes)");
    }

    /**
     * Generate XYZ tiles from reprojected orthophoto file.
     */
    private function generateOrthoXYZTiles(TerrainConversionService $service): void
    {
        if (! $this->terrainDetail->has_ortho) {
            return;
        }

        $this->logStep(6, 'Generating XYZ tiles from orthophoto...');

        try {
            $orthoFile = $this->conversionJob->orthoFile;
            if (! $orthoFile instanceof SourceFile) {
                throw new \Exception('Ortho file not found or invalid for XYZ tiles generation.');
            }
            $inputPath = $orthoFile->storage_path;

            $orthoTilesPath = $service->generateOrthoTiles($inputPath, (string) $this->layer->id);

            $this->terrainDetail->update([
                'ortho_tiles_path' => $orthoTilesPath,
                'ortho_min_zoom' => 10,
                'ortho_max_zoom' => 20,
            ]);

            $this->logStepSuccess(6, 'XYZ Tiles Generation', "Path: {$orthoTilesPath}");
        } catch (Throwable $e) {
            Log::channel($this->getLogChannel())->error('XYZ tiles generation failed: '.$e->getMessage());
            throw $e;
        }
    }

    private function finalizeJob(array $bbox): void
    {
        $step = $this->terrainDetail->has_ortho ? 7 : 6;
        $this->logStep($step, 'Finalizing job records...');

        $this->conversionJob->update([
            'status' => JobStatus::DONE,
            'finished_at' => now(),
        ]);

        $tilesetUrl = "geodata/terrain/{$this->layer->id}";

        $this->terrainDetail->update([
            'native_bounding_box' => $bbox,
            'tileset_path' => "terrain-tiles/{$this->layer->id}",
            'tileset_url' => $tilesetUrl,
        ]);

        $this->layer->update([
            'status' => LayerStatus::PUBLISHED,
            'published_at' => $this->layer->published_at ?? now(),
        ]);

        $this->logStepSuccess($step, 'Finalization');
    }

    private function completePipeline(): void
    {
        $this->logEntry?->update([
            'finished_at' => now(),
            'exit_code' => 0,
            'output_size' => $this->getDirectorySize(storage_path("app/public/terrain-tiles/{$this->layer->id}")),
        ]);

        Log::channel($this->getLogChannel())->info("Finished pipeline successfully for Job #{$this->conversionJob->id}");
    }

    private function cleanupTemporaryFile(string $wgs84Path): void
    {
        $originalPath = $this->sourceFile->storage_path;
        if ($originalPath && $originalPath !== $wgs84Path && Storage::disk('geodata')->exists($originalPath)) {
            Storage::disk('geodata')->delete($originalPath);
            Log::channel($this->getLogChannel())->info("Deleted original source DEM file: {$originalPath}");
        }

        $newSize = Storage::disk('geodata')->exists($wgs84Path) ? Storage::disk('geodata')->size($wgs84Path) : $this->sourceFile->file_size;

        $this->sourceFile->update([
            'storage_path' => $wgs84Path,
            'stored_name' => basename($wgs84Path),
            'file_size' => $newSize,
        ]);

        Log::channel($this->getLogChannel())->info("[STEP INFO] Deleted temp file (SourceFile {$this->sourceFile->id}) used for WGS84 version: {$wgs84Path} (New size: {$newSize} bytes)");
    }

    private function getDirectorySize(string $path): int
    {
        if (! is_dir($path)) {
            return 0;
        }
        $size = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path)) as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }
}
