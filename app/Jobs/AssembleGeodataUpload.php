<?php

namespace App\Jobs;

use App\Enums\JobStatus;
use App\Enums\LayerType;
use App\Enums\SourceFileStatus;
use App\Models\ConversionJob;
use App\Models\SourceFile;
use App\Models\UploadSession;
use App\Services\Upload\FileAssemblyService;

class AssembleGeodataUpload extends BaseConversionJob
{
    public function __construct(ConversionJob $job, string $name, protected ?string $sourceEpsg = null, ?string $date = null)
    {
        parent::__construct($job, $name, $date);
        $this->onQueue('upload');
    }

    protected function getLogChannel(): string
    {
        return $this->conversionJob->layer_type === LayerType::TERRAIN ? 'terrain_conversion' : 'vector_conversion';
    }

    protected function getTotalSteps(): int
    {
        return 1;
    }

    public function handle(FileAssemblyService $assembly): void
    {
        if ($this->conversionJob->fresh()->status === JobStatus::DONE) {
            return;
        }
        $this->conversionJob->update(['status' => JobStatus::PROCESSING, 'error_message' => null, 'finished_at' => null]);
        foreach (UploadSession::where('conversion_job_id', $this->conversionJob->id)->get() as $session) {
            $assembly->assemble($session);
        }
        $conversion = $this->conversionJob->layer_type === LayerType::TERRAIN
            ? new ProcessGeotiffTerrain($this->conversionJob, $this->layerName, $this->acquisitionDate)
            : new ProcessVectorLayer($this->conversionJob, $this->layerName, $this->sourceEpsg, $this->acquisitionDate);
        // One queue reservation owns assembly and conversion; no hand-off window.
        if ($this->job) {
            $conversion->setJob($this->job);
        }
        app()->call([$conversion, 'handle']);
    }

    public function failed(\Throwable $e): void
    {
        parent::failed($e);
        UploadSession::where('conversion_job_id', $this->conversionJob->id)->update(['status' => 'failed']);
        SourceFile::whereIn('id', array_filter([$this->conversionJob->source_file_id, $this->conversionJob->ortho_file_id]))
            ->where('status', SourceFileStatus::UPLOADING->value)->update(['status' => SourceFileStatus::INVALID->value]);
    }
}
