<?php

namespace App\Http\Controllers;

use App\Enums\JobStatus;
use App\Enums\LayerType;
use App\Models\ConversionJob;
use App\Models\UploadSession;
use App\Services\Upload\UploadCompletionService;
use App\Services\Upload\UploadSessionService;
use Illuminate\Http\Request;

class GeodataUploadController extends Controller
{
    public function __construct(protected UploadSessionService $sessions, protected UploadCompletionService $completion) {}

    public function start(Request $request)
    {
        $data = $request->validate([
            'fileName' => 'required|string|max:255',
            'requestId' => 'nullable|uuid',
            'fileSize' => 'required|integer|min:1|max:'.((int) config('geodata.max_upload_mb') * 1024 * 1024),
        ]);
        $session = $this->sessions->start($request->user()->id, $data['fileName'], (int) $data['fileSize'], $data['requestId'] ?? null);

        return response()->json(['success' => true, 'session' => $this->sessionData($session)], 201);
    }

    public function session(Request $request, string $identifier)
    {
        $session = $this->sessions->owned($identifier, $request->user()->id);
        abort_if($session->expires_at->isPast() && $session->status === 'uploading', 410, 'Upload expired.');

        return response()->json(['success' => true, 'session' => $this->sessionData($session)])->header('Cache-Control', 'no-store');
    }

    public function cancel(Request $request, string $identifier)
    {
        $this->sessions->cancel($identifier, $request->user()->id);

        return response()->json(['success' => true]);
    }

    private function sessionData(UploadSession $session): array
    {
        return [
            'identifier' => $session->id, 'fileName' => $session->file_name,
            'fileSize' => $session->file_size, 'chunkSize' => $session->chunk_size,
            'totalChunks' => $session->total_chunks, 'chunks' => (object) $session->chunks,
            'status' => $session->status, 'job_id' => $session->conversion_job_id,
            'expiresAt' => $session->expires_at->toIso8601String(),
        ];
    }

    public function uploadChunk(Request $request)
    {
        $data = $request->validate([
            'file' => 'required|file|max:'.(int) ceil(config('geodata.upload.chunk_bytes') / 1024),
            'identifier' => 'required|uuid',
            'chunkIndex' => 'required|integer|min:0',
            'checksum' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
        ]);
        $this->sessions->storeChunk($data['identifier'], $request->user()->id, $request->file('file'), (int) $data['chunkIndex'], $data['checksum']);

        return response()->json(['success' => true]);
    }

    public function assembleRaster(Request $request)
    {
        $data = $request->validate($this->completionRules() + [
            'demIdentifier' => 'required|uuid',
            'orthoIdentifier' => 'nullable|uuid|different:demIdentifier',
        ]);

        return $this->queueCompletion($request, $data, LayerType::TERRAIN);
    }

    public function assembleVector(Request $request)
    {
        $data = $request->validate($this->completionRules() + ['identifier' => 'required|uuid']);

        return $this->queueCompletion($request, $data, LayerType::VECTOR);
    }

    private function completionRules(): array
    {
        return [
            'layerName' => 'required|string|max:255',
            'sourceEpsg' => ['nullable', 'string', 'regex:/^EPSG:[1-9][0-9]{2,6}$/'],
            'acquisitionDate' => 'nullable|date_format:Y-m-d',
            'layer_category_id' => 'nullable|exists:layer_categories,id',
        ];
    }

    private function queueCompletion(Request $request, array $data, LayerType $type)
    {
        $job = $this->completion->complete($data, $type, $request->user()->id);

        return response()->json(['success' => true, 'message' => 'Upload queued for verification and processing.', 'job_id' => $job->id], 202);
    }

    public function status(ConversionJob $job)
    {
        abort_unless($job->layer?->user_id === auth()->id(), 404);
        $data = [
            'success' => true,
            'status' => $job->status,
            'error_message' => $job->status === JobStatus::FAILED ? 'File processing failed. Please contact an administrator with the job ID.' : null,
            'finished_at' => $job->finished_at ? $job->finished_at->toIso8601String() : null,
        ];

        if ($job->status === JobStatus::DONE) {
            $layer = $job->layer;
            if ($layer) {
                $data['layer'] = [
                    'id' => $layer->id,
                    'name' => $layer->name,
                    'type' => $layer->layer_type->value,
                    'created_at' => $layer->created_at->diffForHumans(),
                ];

                if ($layer->layer_type === LayerType::TERRAIN) {
                    $data['layer']['terrain_detail'] = $layer->terrainDetail ? [
                        'tileset_url' => $layer->terrainDetail->tileset_url,
                        'has_ortho' => $layer->terrainDetail->has_ortho,
                        'native_bounding_box' => $layer->terrainDetail->native_bounding_box,
                        'ortho_tiles_url' => $layer->terrainDetail->ortho_tiles_url,
                        'ortho_min_zoom' => $layer->terrainDetail->ortho_min_zoom,
                        'ortho_max_zoom' => $layer->terrainDetail->ortho_max_zoom,
                    ] : null;
                } elseif ($layer->layer_type === LayerType::VECTOR) {
                    $data['layer']['vector_detail'] = $layer->vectorDetail ? [
                        'geometry_type' => $layer->vectorDetail->geometry_type,
                        'feature_count' => $layer->vectorDetail->feature_count,
                        'native_bounding_box' => $layer->vectorDetail->native_bounding_box,
                        'acquisition_date' => $layer->vectorDetail->acquisition_date ? $layer->vectorDetail->acquisition_date->format('d M Y') : 'N/A',
                    ] : null;
                }
            }

            $sourceFile = $job->sourceFile;
            if ($sourceFile) {
                $data['source_file'] = [
                    'name' => $sourceFile->original_name,
                    'size' => $sourceFile->file_size,
                ];
            }
        }

        return response()->json($data);
    }
}
