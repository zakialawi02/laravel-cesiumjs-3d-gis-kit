<?php

namespace App\Http\Controllers;

use App\Enums\JobStatus;
use App\Enums\LayerType;
use App\Jobs\CleanupLayerFilesJob;
use App\Models\ConversionJob;
use App\Models\Layer;
use App\Models\SourceFile;
use App\Services\Database\DynamicTableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class GeodataController extends Controller
{
    /**
     * A queued/processing job untouched for longer than this is treated as dead and may be deleted.
     * Must exceed the longest conversion timeout (ProcessGeotiffTerrain: 7200 s).
     */
    private const STALE_JOB_SECONDS = 3 * 60 * 60;

    /**
     * Delete a layer with its job, source files, generated tiles and PostGIS table.
     *
     * Every output path is derived from the layer id, not only from detail columns that conversion
     * jobs fill at the very end, so layers that failed or never finished are cleaned up completely.
     * Database rows are removed in a transaction; disk/table cleanup runs after commit on the queue.
     */
    public function destroy(Layer $layer, DynamicTableService $tables): JsonResponse
    {
        if ($this->isConversionRunning($layer->job)) {
            return response()->json([
                'success' => false,
                'message' => 'This layer is still being processed. Wait until its conversion finishes or fails before deleting it.',
            ], 409);
        }

        $layerId = (string) $layer->id;
        $layerType = $layer->layer_type->value;
        $directoriesToDelete = [];
        $filesToDelete = [];
        $physicalTableName = null;

        if ($layer->layer_type === LayerType::TERRAIN) {
            $directoriesToDelete = ["terrain-tiles/{$layerId}", "xyz-tiles/{$layerId}_ortho"];
        } else {
            $physicalTableName = $tables->generateTableName($layerId);
            $filesToDelete[] = "geojson/vector_{$layerId}.geojson";
        }

        DB::transaction(function () use ($layer, &$directoriesToDelete, &$filesToDelete, &$physicalTableName): void {
            $job = $layer->job;
            $files = collect([$job?->sourceFile, $job?->orthoFile]);

            if ($layer->layer_type === LayerType::TERRAIN && ($detail = $layer->terrainDetail)) {
                $files->push($detail->sourceFile, $detail->orthoFile);
                $directoriesToDelete = array_merge($directoriesToDelete, array_filter([$detail->tileset_path, $detail->ortho_tiles_path]));
            } elseif ($layer->layer_type === LayerType::VECTOR && ($detail = $layer->vectorDetail)) {
                $files->push($detail->sourceFile);
                $physicalTableName = $detail->physical_table_name ?: $physicalTableName;

                if ($detail->output_path) {
                    $filesToDelete[] = str_starts_with($detail->output_path, 'geodata/')
                        ? substr($detail->output_path, 8)
                        : $detail->output_path;
                }
            }

            // Layer references the job with "restrict", so it must go first.
            $layer->delete();

            if ($job) {
                $job->logs()->delete();
                DB::table('upload_sessions')->where('conversion_job_id', $job->id)->delete();
                $job->delete();
            }

            $isTerrain = $layer->layer_type === LayerType::TERRAIN;

            $files->filter()->unique('id')->each(function (SourceFile $file) use (&$filesToDelete, $isTerrain): void {
                $filesToDelete[] = $file->storage_path;

                if ($isTerrain) {
                    // Reprojected copy written next to the source by TerrainConversionService::reproject().
                    $filesToDelete[] = $file->storage_path.'_wgs84.tif';
                }

                $file->delete();
            });
        });

        CleanupLayerFilesJob::dispatch(
            array_values(array_unique($directoriesToDelete)),
            [],
            array_values(array_unique($filesToDelete)),
            $physicalTableName,
            $layerType,
        )->onQueue('default');

        return response()->json(['success' => true, 'message' => 'Layer deleted.']);
    }

    /**
     * True while a conversion may still write outputs for this layer.
     */
    private function isConversionRunning(?ConversionJob $job): bool
    {
        if ($job === null || ! in_array($job->status, [JobStatus::QUEUED, JobStatus::PROCESSING], true)) {
            return false;
        }

        return $job->updated_at === null || $job->updated_at->diffInSeconds(now()) < self::STALE_JOB_SECONDS;
    }
}
