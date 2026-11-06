<?php

namespace App\Jobs;

use App\Services\Database\DynamicTableService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanupLayerFilesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $directoriesToDelete;

    public $filesToDeletePublic;

    public $filesToDelete;

    public $physicalTableName;

    public $layerType;

    /**
     * Create a new job instance.
     */
    public function __construct(
        array $directoriesToDelete,
        array $filesToDeletePublic,
        array $filesToDelete,
        ?string $physicalTableName = null,
        ?string $layerType = null
    ) {
        $this->directoriesToDelete = $directoriesToDelete;
        $this->filesToDeletePublic = $filesToDeletePublic;
        $this->filesToDelete = $filesToDelete;
        $this->physicalTableName = $physicalTableName;
        $this->layerType = $layerType;
        $this->afterCommit = true;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // 1. Drop PostGIS physical table
        if ($this->physicalTableName) {
            try {
                app(DynamicTableService::class)->dropTable($this->physicalTableName);
            } catch (\Exception $e) {
                Log::error('Background PostGIS table drop failed: '.$e->getMessage(), [
                    'table_name' => $this->physicalTableName,
                ]);
            }
        }

        // 2. Delete files on disk
        try {
            foreach ($this->directoriesToDelete as $dir) {
                if (Storage::disk('public')->exists($dir)) {
                    Storage::disk('public')->deleteDirectory($dir);
                }
            }
            foreach ($this->filesToDeletePublic as $file) {
                if (Storage::disk('public')->exists($file)) {
                    Storage::disk('public')->delete($file);
                }
            }
            foreach ($this->filesToDelete as $file) {
                if (Storage::disk('geodata')->exists($file)) {
                    Storage::disk('geodata')->delete($file);
                }
            }
        } catch (\Exception $e) {
            Log::error('Background file cleanup failed: '.$e->getMessage(), [
                'directories' => $this->directoriesToDelete,
                'files_public' => $this->filesToDeletePublic,
                'files_geodata' => $this->filesToDelete,
            ]);

            throw $e;
        }
    }
}
