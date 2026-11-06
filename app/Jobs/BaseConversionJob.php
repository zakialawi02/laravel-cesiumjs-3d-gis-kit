<?php

namespace App\Jobs;

use App\Enums\JobStatus;
use App\Enums\LayerStatus;
use App\Models\ConversionJob;
use App\Models\ConversionLog;
use App\Models\Layer;
use App\Models\SourceFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

abstract class BaseConversionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 7200;

    public function middleware(): array
    {
        return [(new WithoutOverlapping('geodata:'.$this->conversionJob->id))
            ->shared()->releaseAfter(60)->expireAfter(7400)];
    }

    protected ?ConversionLog $logEntry = null;

    protected ?Layer $layer = null;

    protected ?SourceFile $sourceFile = null;

    public function __construct(
        public ConversionJob $conversionJob,
        protected string $layerName,
        protected ?string $acquisitionDate = null
    ) {
        $this->onQueue('conversion');
    }

    /**
     * Get the log channel name for this specific job.
     */
    abstract protected function getLogChannel(): string;

    /**
     * Get the total number of steps for logging.
     */
    abstract protected function getTotalSteps(): int;

    protected function logStep(int $step, string $message): void
    {
        $total = $this->getTotalSteps();
        Log::channel($this->getLogChannel())->info("[STEP {$step}/{$total}] {$message}");
    }

    protected function logStepSuccess(int $step, string $operation, string $extra = ''): void
    {
        $total = $this->getTotalSteps();
        $stepLabel = $step > 0 ? "[STEP {$step}/{$total}]" : '[STEP EXTRA]';
        Log::channel($this->getLogChannel())->info("{$stepLabel} {$operation} SUCCESS".($extra ? ". {$extra}" : ''));
    }

    protected function logStepExtra(string $message): void
    {
        Log::channel($this->getLogChannel())->info("[STEP EXTRA] {$message}");
    }

    protected function handleFailure(\Throwable $e): void
    {
        $channel = $this->getLogChannel();
        Log::channel($channel)->error('Pipeline FAILED: '.$e->getMessage());

        $this->cleanupDockerContainers();

        // A recoverable attempt must not make the UI report terminal failure.
        $willRetry = $this->job && $this->attempts() < $this->tries && ! $this->job->hasFailed();

        try {
            $this->logEntry?->update([
                'finished_at' => now(),
                'exit_code' => 1,
                'stderr_log' => $e->getMessage()."\n".$e->getTraceAsString(),
            ]);
        } catch (\Throwable $updateError) {
            Log::channel($channel)->error('Failed to update logEntry during job failure handling: '.$updateError->getMessage());
        }

        if ($willRetry) {
            return;
        }

        try {
            $this->conversionJob->update([
                'status' => JobStatus::FAILED,
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);
        } catch (\Throwable $updateError) {
            Log::channel($channel)->error('Failed to update conversionJob status during job failure handling: '.$updateError->getMessage());
        }

        try {
            $layerToUpdate = $this->layer ?? $this->conversionJob->layer;
            if ($layerToUpdate) {
                $layerToUpdate->update(['status' => LayerStatus::PROCESS_FAILED]);
            }
        } catch (\Throwable $updateError) {
            Log::channel($channel)->error('Failed to update layer status during job failure handling: '.$updateError->getMessage());
        }
    }

    /**
     * Handle a job failure triggered by the queue worker (e.g., timeout or unhandled exception).
     */
    public function failed(\Throwable $e): void
    {
        $this->handleFailure($e);
    }

    protected function cleanupDockerContainers(): void
    {
        try {
            if (! $this->conversionJob || ! $this->conversionJob->id) {
                return;
            }
            $jobId = $this->conversionJob->id;

            $process = Process::run('docker ps -q --filter "label=conversion_job_id='.escapeshellarg($jobId).'"');
            $containerIds = trim($process->output());

            if (! empty($containerIds)) {
                $ids = explode("\n", str_replace("\r", '', $containerIds));
                foreach ($ids as $id) {
                    $id = trim($id);
                    if ($id) {
                        Process::run('docker kill '.escapeshellarg($id));
                        Log::channel($this->getLogChannel())->info('Killed orphaned Docker container: '.$id);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::channel($this->getLogChannel())->error('Failed to cleanup Docker containers: '.$e->getMessage());
        }
    }
}
