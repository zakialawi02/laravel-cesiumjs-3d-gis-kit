<?php

namespace App\Jobs;

use App\Enums\JobStatus;
use App\Enums\LayerStatus;
use App\Models\ConversionJob;
use App\Models\ConversionLog;
use App\Models\VectorDetail;
use App\Services\Conversion\DwgImportService;
use App\Services\Conversion\DxfColorInjector;
use App\Services\Conversion\GdalService;
use App\Services\Conversion\ShpImportService;
use App\Services\Conversion\SurpacStrParserService;
use App\Services\Database\DynamicTableService;
use App\Services\Database\SpatialImportService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessVectorLayer extends BaseConversionJob
{
    public int $timeout = 600; // 10 minutes

    protected ?VectorDetail $vectorDetail = null;

    protected ?string $sourceEpsg = null;

    public function __construct(
        ConversionJob $conversionJob,
        string $layerName,
        ?string $sourceEpsg = null,
        ?string $acquisitionDate = null
    ) {
        parent::__construct($conversionJob, $layerName, $acquisitionDate);
        $this->sourceEpsg = $sourceEpsg;
    }

    protected function getLogChannel(): string
    {
        return 'vector_conversion';
    }

    protected function getTotalSteps(): int
    {
        return 7;
    }

    public function handle(
        SurpacStrParserService $strParser,
        GdalService $gdalService,
        ShpImportService $shpService,
        DwgImportService $dwgService,
        DxfColorInjector $dxfColorInjector,
        SpatialImportService $spatialImport,
        DynamicTableService $dynTable
    ): void {
        $gdalService->setJobId($this->conversionJob->id);

        try {
            $this->initializePipeline();

            $wgs84GeojsonPath = $this->createDatabaseRecords();
            $this->convertToWgs84GeoJson($strParser, $gdalService, $shpService, $dwgService, $wgs84GeojsonPath);
            $this->injectDxfColors($dxfColorInjector, $wgs84GeojsonPath);
            $attributes = $this->scanGeoJsonSchema($spatialImport, $wgs84GeojsonPath);
            $tableName = $this->createDynamicTable($dynTable, $attributes);
            $summary = $this->importToPhysicalTable($spatialImport, $wgs84GeojsonPath, $tableName);
            $this->updateVectorDetail($summary, $tableName);

            $this->finalizeJob($wgs84GeojsonPath);
        } catch (\Throwable $e) {
            $this->handleFailure($e);
            throw $e;
        }
    }

    private function initializePipeline(): void
    {
        $this->conversionJob->update([
            'status' => JobStatus::PROCESSING,
            'conversion_tool' => 'gdal/ogr2ogr',
            'started_at' => now(),
        ]);

        $this->logEntry = ConversionLog::create([
            'job_id' => $this->conversionJob->id,
            'attempt' => $this->attempts(),
            'started_at' => now(),
        ]);

        $this->sourceFile = $this->conversionJob->sourceFile;

        Log::channel($this->getLogChannel())->info("Starting vector pipeline for Job #{$this->conversionJob->id} (File: {$this->sourceFile->original_name} - ID: {$this->sourceFile->id})");
    }

    private function createDatabaseRecords(): string
    {
        $this->logStep(1, 'Retrieving layer and creating vector detail records...');

        $this->layer = $this->conversionJob->layer;

        if (! $this->layer) {
            throw new \Exception("Layer record not found for Job #{$this->conversionJob->id}");
        }

        $this->vectorDetail = VectorDetail::updateOrCreate(
            ['layer_id' => $this->layer->id],
            [
                'source_file_id' => $this->sourceFile->id,
                'source_format' => $this->sourceFile->file_type->value,
                'source_epsg' => $this->sourceEpsg,
                'acquisition_date' => $this->acquisitionDate,
                'srs' => 'EPSG:4326', // Target
            ]
        );

        $this->logStepSuccess(1, 'Database Records');

        $wgs84GeojsonPath = storage_path('app/geodata/geojson/vector_'.$this->layer->id.'.geojson');
        if (! is_dir(dirname($wgs84GeojsonPath))) {
            mkdir(dirname($wgs84GeojsonPath), 0777, true);
        }

        return $wgs84GeojsonPath;
    }

    private function convertToWgs84GeoJson(
        SurpacStrParserService $strParser,
        GdalService $gdalService,
        ShpImportService $shpService,
        DwgImportService $dwgService,
        string $wgs84GeojsonPath
    ): void {
        $this->logStep(2, 'Converting source file to WGS84 GeoJSON...');

        $inputPath = Storage::disk('geodata')->path($this->sourceFile->storage_path);

        $fileType = $this->sourceFile->file_type->value;

        if ($fileType === 'str') {
            $intermediatePath = storage_path('app/geodata/geojson/str_int_'.$this->layer->id.'.geojson');
            try {
                $strParser->writeGeoJson($inputPath, $intermediatePath);
                $gdalService->reprojectToWgs84($intermediatePath, $wgs84GeojsonPath, $this->sourceEpsg);
            } finally {
                if (is_file($intermediatePath)) {
                    unlink($intermediatePath);
                }
            }
        } elseif ($fileType === 'dwg' || $fileType === 'dxf') {
            $dwgService->process($inputPath, $wgs84GeojsonPath, $this->sourceEpsg);
        } elseif ($fileType === 'shapefile') {
            $shpService->process($inputPath, $wgs84GeojsonPath);
        } else {
            // Default ke GdalService untuk format vector lain
            $gdalService->reprojectToWgs84($inputPath, $wgs84GeojsonPath, $this->sourceEpsg);
        }

        $this->logStepSuccess(2, 'WGS84 Conversion');
    }

    /**
     * For DXF/DWG sources, inject ACI color codes from the original file into the GeoJSON.
     * This preserves per-entity color information that GDAL/ogr2ogr does not output.
     */
    private function injectDxfColors(DxfColorInjector $dxfColorInjector, string $wgs84GeojsonPath): void
    {
        $fileType = $this->sourceFile->file_type->value;

        if (! in_array($fileType, ['dxf', 'dwg'])) {
            return;
        }

        $inputPath = Storage::disk('geodata')->path($this->sourceFile->storage_path);

        // Only process .dxf files (DWG binary format cannot be parsed directly)
        if ($fileType !== 'dxf') {
            Log::channel($this->getLogChannel())->info('Skipping DXF color injection for DWG file (binary format).');

            return;
        }

        $this->logStepExtra('Injecting DXF entity colors into GeoJSON...');

        try {
            $injected = $dxfColorInjector->inject($inputPath, $wgs84GeojsonPath);
            if ($injected) {
                $this->logStepExtra('DXF color injection completed successfully.');
            } else {
                $this->logStepExtra('DXF color injection skipped (no colors found or not applicable).');
            }
        } catch (\Throwable $e) {
            // Non-critical: log and continue without color info
            Log::channel($this->getLogChannel())->warning('DXF color injection failed (non-critical): '.$e->getMessage());
            $this->logStepExtra('DXF color injection FAILED (non-critical): '.$e->getMessage());
        }
    }

    private function scanGeoJsonSchema(SpatialImportService $spatialImport, string $wgs84GeojsonPath): array
    {
        $this->logStep(3, 'Scanning GeoJSON for properties schema...');
        $attributes = $spatialImport->getPropertiesSchema($wgs84GeojsonPath);
        $this->logStepSuccess(3, 'Schema Scanned', 'Found '.count($attributes).' attributes');

        return $attributes;
    }

    private function createDynamicTable(DynamicTableService $dynTable, array $attributes): string
    {
        $this->logStep(4, 'Creating dynamic physical PostGIS table with dedicated columns...');
        $tableName = $dynTable->createTable($this->layer->id, $attributes);
        $this->logStepSuccess(4, 'Dynamic Table Created', "Table: {$tableName}");

        return $tableName;
    }

    private function importToPhysicalTable(SpatialImportService $spatialImport, string $wgs84GeojsonPath, string $tableName): array
    {
        $this->logStep(5, 'Importing features into physical table...');
        $summary = $spatialImport->import($wgs84GeojsonPath, $tableName);
        $this->logStepSuccess(5, 'Import Completed', 'Features: '.$summary['feature_count']);

        return $summary;
    }

    private function updateVectorDetail(array $summary, string $tableName): void
    {
        $this->logStep(6, 'Updating vector detail with spatial summary...');
        $this->vectorDetail->update([
            'feature_count' => $summary['feature_count'],
            'geometry_type' => $summary['geometry_type'],
            'native_bounding_box' => $summary['bbox'],
            'physical_table_name' => $tableName,
            'output_path' => 'geodata/geojson/vector_'.$this->layer->id.'.geojson',
        ]);
        $this->logStepSuccess(6, 'Spatial Update');
    }

    private function finalizeJob(string $wgs84GeojsonPath): void
    {
        $this->logStep(7, 'Finalizing job...');

        $outputSize = 0;
        if (file_exists($wgs84GeojsonPath)) {
            $outputSize = filesize($wgs84GeojsonPath);
            // Do not delete the GeoJSON file as requested by the user, keep it saved in the folder
            Log::channel($this->getLogChannel())->info("Preserving GeoJSON vector output at: {$wgs84GeojsonPath} (Size: {$outputSize} bytes)");
        }

        // Update the source file's size because it has been processed
        $sourcePath = $this->sourceFile->storage_path;
        if (Storage::disk('geodata')->exists($sourcePath)) {
            $currentSize = Storage::disk('geodata')->size($sourcePath);
            $this->sourceFile->update([
                'file_size' => $currentSize,
            ]);
            Log::channel($this->getLogChannel())->info("SourceFile #{$this->sourceFile->id} size updated/recalculated to: {$currentSize} bytes");
        }

        $this->layer->update([
            'status' => LayerStatus::PUBLISHED,
            'published_at' => now(),
        ]);

        $this->conversionJob->update([
            'status' => JobStatus::DONE,
            'finished_at' => now(),
        ]);

        $this->logEntry?->update([
            'finished_at' => now(),
            'exit_code' => 0,
            'output_size' => $outputSize,
        ]);

        $this->logStepSuccess(7, 'Finalization');
        Log::channel($this->getLogChannel())->info("Finished vector pipeline successfully for Job #{$this->conversionJob->id}");
    }
}
