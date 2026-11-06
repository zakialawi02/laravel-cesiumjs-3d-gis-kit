<?php

namespace App\Services\Upload;

use App\Enums\JobStatus;
use App\Enums\LayerStatus;
use App\Enums\LayerType;
use App\Enums\SourceFileStatus;
use App\Enums\SourceFileType;
use App\Jobs\AssembleGeodataUpload;
use App\Models\ConversionJob;
use App\Models\Layer;
use App\Models\LayerCategory;
use App\Models\SourceFile;
use App\Models\UploadSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UploadCompletionService
{
    public function __construct(protected UploadSessionService $sessions) {}

    public function complete(array $data, LayerType $type, string $userId): ConversionJob
    {
        $primaryId = $type === LayerType::TERRAIN ? $data['demIdentifier'] : $data['identifier'];
        $orthoId = $data['orthoIdentifier'] ?? null;
        $ids = array_filter([$primaryId, $orthoId]);
        sort($ids);
        // Hash only immutable, normalized completion parameters, not client filenames/counts.
        $parameters = [
            'type' => $type->value, 'primary' => $primaryId, 'ortho' => $orthoId,
            'name' => $data['layerName'], 'epsg' => $data['sourceEpsg'] ?? null,
            'date' => $data['acquisitionDate'] ?? null, 'category' => $data['layer_category_id'] ?? null,
        ];
        $hash = hash('sha256', json_encode($parameters, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($data, $type, $userId, $ids, $primaryId, $orthoId, $hash) {
            $sessions = collect($ids)->mapWithKeys(fn ($id) => [$id => $this->sessions->owned($id, $userId, true)]);
            $primary = $sessions[$primaryId];
            if ($primary->conversion_job_id) {
                foreach ($sessions as $session) {
                    abort_unless($session->conversion_job_id === $primary->conversion_job_id && $session->completion_hash === $hash, 409, 'Upload was already submitted with different parameters.');
                }

                return ConversionJob::findOrFail($primary->conversion_job_id);
            }
            foreach ($sessions as $session) {
                $this->sessions->assertComplete($session);
                $extension = strtolower(pathinfo($session->file_name, PATHINFO_EXTENSION));
                $allowed = $type === LayerType::TERRAIN ? ['tif', 'tiff', 'geotiff'] : ['geojson', 'zip', 'str', 'dxf', 'dwg'];
                abort_unless(in_array($extension, $allowed, true), 422, 'File format does not match this upload workflow.');
                if (in_array($extension, ['str', 'dxf', 'dwg'], true)) {
                    abort_if(empty($data['sourceEpsg']), 422, 'Source EPSG is required for CAD/STR files.');
                }
            }
            $source = $this->createSource($primary);
            $orthoSource = $orthoId ? $this->createSource($sessions[$orthoId]) : null;
            $job = ConversionJob::create([
                'layer_type' => $type, 'source_file_id' => $source->id,
                'ortho_file_id' => $orthoSource?->id, 'status' => JobStatus::QUEUED,
                'conversion_tool' => 'upload/assembly',
            ]);
            Layer::create([
                'user_id' => $userId, 'job_id' => $job->id, 'name' => $data['layerName'],
                'slug' => Str::slug($data['layerName']).'-'.Str::random(8),
                'layer_type' => $type, 'status' => LayerStatus::PROCESSING,
                'layer_category_id' => $data['layer_category_id'] ?? LayerCategory::where('slug', 'other')->value('id'),
            ]);
            foreach ($sessions as $session) {
                $session->update(['status' => 'queued', 'conversion_job_id' => $job->id, 'completion_hash' => $hash]);
            }
            $dispatch = AssembleGeodataUpload::dispatch($job, $data['layerName'], $data['sourceEpsg'] ?? null, $data['acquisitionDate'] ?? null);
            // With the default database queue, persist the queue row in the same
            // transaction as the layer/session. Other brokers must wait for commit.
            if (config('queue.default') === 'database' && (! config('queue.connections.database.connection')
                || config('queue.connections.database.connection') === config('database.default'))) {
                $dispatch->beforeCommit();
            } else {
                $dispatch->afterCommit();
            }

            return $job;
        });
    }

    private function createSource(UploadSession $session): SourceFile
    {
        $extension = strtolower(pathinfo($session->file_name, PATHINFO_EXTENSION));
        $id = (string) Str::ulid();
        $subdirectory = in_array($extension, ['tif', 'tiff', 'geotiff'], true) ? 'geotiff' : 'vectors';
        $source = new SourceFile([
            'original_name' => $session->file_name, 'stored_name' => $id.'.'.$extension,
            'file_type' => $extension === 'zip' ? SourceFileType::SHAPEFILE : SourceFileType::from($extension),
            'file_size' => $session->file_size, 'mime_type' => 'application/octet-stream',
            'storage_path' => $subdirectory.'/'.$id.'.'.$extension,
            'checksum_sha256' => str_repeat('0', 64), 'status' => SourceFileStatus::UPLOADING,
        ]);
        $source->id = $id;
        $source->save();
        $session->update(['source_file_id' => $id]);

        return $source;
    }
}
