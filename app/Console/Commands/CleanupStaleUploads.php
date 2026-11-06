<?php

namespace App\Console\Commands;

use App\Enums\SourceFileStatus;
use App\Models\SourceFile;
use App\Models\UploadSession;
use App\Services\Upload\UploadSessionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class CleanupStaleUploads extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'geodata:cleanup-stale {--hours=20}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup source files stuck in uploading/invalid status and orphaned temp chunks';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        if ($hours < 1) {
            $this->error('The hours option must be positive.');

            return self::FAILURE;
        }

        // Lock the session row against chunk writes and finalization. Queued work
        // owns its inputs until the worker finishes, irrespective of upload TTL.
        UploadSession::where('expires_at', '<', now())->where('status', '!=', 'queued')
            ->eachById(function ($candidate) {
                DB::transaction(function () use ($candidate) {
                    $session = UploadSession::whereKey($candidate->id)->lockForUpdate()->first();
                    if (! $session || $session->status === 'queued' || $session->expires_at->isFuture()) {
                        return;
                    }
                    File::deleteDirectory(app(UploadSessionService::class)->directory($session));
                    $session->delete();
                });
            });

        // 1. Cleanup stale SourceFile records and their files
        $stale = SourceFile::whereIn('status', [
            SourceFileStatus::UPLOADING,
            SourceFileStatus::INVALID,
        ])
            ->where('created_at', '<', now()->subHours($hours))
            ->whereDoesntHave('conversionJobs', fn ($q) => $q->whereIn('status', ['queued', 'processing']))
            ->whereDoesntHave('orthoConversionJobs', fn ($q) => $q->whereIn('status', ['queued', 'processing']))
            ->get();

        $count = 0;
        foreach ($stale as $file) {
            $fullPath = Storage::disk('geodata')->path($file->storage_path);
            if (File::exists($fullPath)) {
                File::delete($fullPath);
            }
            File::delete($fullPath.'.assembling');
            // Preserve source metadata referenced by failed conversion jobs.
            if (! $file->conversionJobs()->exists() && ! $file->orthoConversionJobs()->exists()) {
                $file->delete();
            }
            $count++;
        }

        $this->info("Cleaned up {$count} stale upload(s).");

        // 2. Cleanup orphaned temp chunks directories
        $tempChunksPath = Storage::disk('local')->path('temp_chunks');
        $chunkFolderCount = 0;
        if (File::exists($tempChunksPath)) {
            $directories = File::directories($tempChunksPath);
            foreach ($directories as $dir) {
                if (is_link($dir) || UploadSession::whereKey(basename($dir))->exists()) {
                    continue;
                }
                $lastModified = File::lastModified($dir);
                if (now()->subHours($hours)->timestamp > $lastModified) {
                    File::deleteDirectory($dir);
                    $chunkFolderCount++;
                }
            }
        }

        if ($chunkFolderCount > 0) {
            $this->info("Cleaned up {$chunkFolderCount} stale temp chunk directory/directories.");
        }

        return 0;
    }
}
