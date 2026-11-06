<?php

namespace App\Http\Controllers;

use App\Enums\JobStatus;
use App\Enums\LayerStatus;
use App\Enums\LayerType;
use App\Models\ConversionJob;
use App\Models\Layer;
use App\Models\LayerCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GeodataLayerController extends Controller
{
    private const TERRAIN_TILE_REVISION = '2';

    /**
     * Tiles stay on disk until the cleanup job runs, so only serve tiles of a layer that still
     * exists and is published (cached briefly: a map view requests hundreds of tiles).
     */
    private function abortUnlessPublished(string $layerId): void
    {
        $isPublished = Cache::remember("layer-published:{$layerId}", 30, fn (): bool => Layer::query()
            ->whereKey($layerId)
            ->where('status', LayerStatus::PUBLISHED)
            ->exists());

        abort_unless($isPublished, 404);
    }

    /**
     * Serve a quantized-mesh terrain tile, flagging gzip-compressed payloads.
     */
    public function serveTerrainTile(string $layerId, string $z, string $x, string $y): Response
    {
        $this->abortUnlessPublished($layerId);

        $path = "terrain-tiles/{$layerId}/{$z}/{$x}/{$y}.terrain";

        if (! Storage::disk('public')->exists($path)) {
            return response('Terrain tile not found', 404, ['Cache-Control' => 'no-store']);
        }

        $file = Storage::disk('public')->get($path);
        $headers = [
            'Cache-Control' => 'public, max-age=604800',
            'Content-Type' => 'application/octet-stream',
        ];

        if (bin2hex(substr($file, 0, 2)) === '1f8b') {
            $headers['Content-Encoding'] = 'gzip';
        }

        return response($file, 200, $headers);
    }

    /**
     * Serve layer.json for a terrain tileset, versioning tile URLs to bust stale caches.
     */
    public function serveTerrainMetadata(string $layerId): Response
    {
        $this->abortUnlessPublished($layerId);

        $path = "terrain-tiles/{$layerId}/layer.json";

        if (! Storage::disk('public')->exists($path)) {
            return response('Metadata not found', 404);
        }

        $metadata = json_decode(Storage::disk('public')->get($path), true, 512, JSON_THROW_ON_ERROR);

        foreach ($metadata['tiles'] ?? [] as $index => $template) {
            $separator = str_contains($template, '?') ? '&' : '?';
            $metadata['tiles'][$index] = $template.$separator.'terrain_revision='.self::TERRAIN_TILE_REVISION;
        }

        return response(json_encode($metadata, JSON_THROW_ON_ERROR), 200, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache',
        ]);
    }

    /**
     * Serve an XYZ orthophoto tile.
     */
    public function serveOrthoTile(string $layerId, string $z, string $x, string $y): Response
    {
        $this->abortUnlessPublished(Str::beforeLast($layerId, '_ortho'));

        $path = "xyz-tiles/{$layerId}/{$z}/{$x}/{$y}.png";

        if (! Storage::disk('public')->exists($path)) {
            return response('', 404);
        }

        return response(Storage::disk('public')->get($path), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    /**
     * Stream a vector layer from its PostGIS table as a GeoJSON FeatureCollection.
     */
    public function getVectorGeoJson(Layer $layer): JsonResponse|StreamedResponse
    {
        abort_unless($layer->status === LayerStatus::PUBLISHED, 404);

        $detail = $layer->vectorDetail;

        if (! $detail || ! $detail->physical_table_name) {
            return response()->json(['type' => 'FeatureCollection', 'features' => []]);
        }

        abort_unless(preg_match('/^vector_[a-z0-9_]{1,60}$/', $detail->physical_table_name) === 1, 400, 'Invalid table name pattern.');

        return response()->stream(function () use ($detail): void {
            echo '{"type":"FeatureCollection","features":[';
            $first = true;

            $rows = DB::table($detail->physical_table_name)
                ->select('*', DB::raw('ST_AsGeoJSON(geometry) as geometry_json'))
                ->cursor();

            foreach ($rows as $row) {
                $properties = [];

                foreach ($row as $key => $value) {
                    if (in_array($key, ['id', 'geometry', 'geometry_json', 'created_at', 'updated_at'], true)) {
                        continue;
                    }

                    $properties[str_starts_with($key, 'attr_') ? substr($key, 5) : $key] = $value;
                }

                echo ($first ? '' : ',').json_encode([
                    'type' => 'Feature',
                    'id' => $row->id,
                    'geometry' => json_decode($row->geometry_json),
                    'properties' => $properties,
                ]);
                $first = false;
            }

            echo ']}';
        }, 200, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache',
        ]);
    }

    /**
     * Published terrain layers for the "comparison terrain" dropdowns of the Cut & Fill and Profile tools.
     */
    public function listTerrainLayers(Request $request): JsonResponse
    {
        $layers = Layer::query()
            ->where('layer_type', LayerType::TERRAIN)
            ->where('status', LayerStatus::PUBLISHED)
            ->with('terrainDetail')
            ->latest()
            ->limit(min(max($request->integer('limit', 30), 1), 100))
            ->get()
            ->map(fn (Layer $layer): array => [
                'id' => $layer->id,
                'name' => $layer->name,
                'terrain_detail' => $layer->terrainDetail ? [
                    'tileset_url' => $layer->terrainDetail->tileset_url,
                    'acquisition_date' => $layer->terrainDetail->acquisition_date?->format('d M Y') ?? 'N/A',
                ] : null,
            ]);

        return response()->json(['success' => true, 'layers' => $layers]);
    }

    /**
     * List published layers (terrain & vector) grouped by category.
     * Uncategorized layers are merged into the "Other" category.
     */
    public function listAllLayers(Request $request): JsonResponse
    {
        $search = trim((string) $request->string('search'));
        $monthYear = (string) $request->string('month_year');

        $applyFilters = function ($query) use ($search, $monthYear): void {
            $query->where('status', LayerStatus::PUBLISHED)
                ->with(['terrainDetail', 'vectorDetail'])
                ->latest();

            if ($search !== '') {
                $query->whereLike('name', "%{$search}%");
            }

            if (preg_match('/^(\d{4})-(\d{2})$/', $monthYear, $parts)) {
                $query->whereYear('created_at', $parts[1])->whereMonth('created_at', $parts[2]);
            }
        };

        $categories = LayerCategory::query()
            ->orderBy('sort_order')
            ->with(['layers' => $applyFilters])
            ->get();

        $uncategorizedQuery = Layer::query()->whereNull('layer_category_id');
        $applyFilters($uncategorizedQuery);
        $uncategorized = $uncategorizedQuery->get();
        $otherCategory = $categories->firstWhere('slug', 'other');

        if ($uncategorized->isNotEmpty() && $otherCategory) {
            $otherCategory->setRelation('layers', $otherCategory->layers->concat($uncategorized)->sortByDesc('created_at')->values());
        }

        $payload = $categories
            ->filter(fn (LayerCategory $category): bool => $category->layers->isNotEmpty())
            ->map(fn (LayerCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'color' => $category->color,
                'icon_url' => $category->icon_url,
                'layers' => $category->layers->map(fn (Layer $layer): array => $this->formatLayer($layer))->values(),
            ])
            ->values();

        return response()->json(['success' => true, 'categories' => $payload]);
    }

    /**
     * List the latest conversion jobs for the Data panel.
     */
    public function listRecentUploads(): JsonResponse
    {
        $jobs = ConversionJob::query()
            ->with(['sourceFile:id,original_name', 'layer:id,job_id,name,layer_type'])
            ->latest()
            ->take(10)
            ->get()
            ->map(fn (ConversionJob $job): array => [
                'id' => $job->id,
                'status' => $job->status->value,
                'status_label' => $job->status->label(),
                'layer_type' => $job->layer_type->value,
                'layer_name' => $job->layer?->name ?? $job->sourceFile?->original_name ?? 'Processing...',
                'time_diff' => ($job->finished_at ?? $job->created_at)->diffForHumans(),
                'is_done' => $job->status === JobStatus::DONE,
                'is_failed' => $job->status === JobStatus::FAILED,
            ]);

        return response()->json(['success' => true, 'jobs' => $jobs]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatLayer(Layer $layer): array
    {
        $item = [
            'id' => $layer->id,
            'name' => $layer->name,
            'layer_type' => $layer->layer_type->value,
            'created_at' => $layer->created_at->format('d M Y'),
        ];

        if ($layer->layer_type === LayerType::TERRAIN && ($detail = $layer->terrainDetail)) {
            $item['terrain_detail'] = [
                'tileset_url' => $detail->tileset_url,
                'has_ortho' => $detail->has_ortho,
                'native_bounding_box' => $detail->native_bounding_box,
                'acquisition_date' => $detail->acquisition_date?->format('d M Y') ?? 'N/A',
                'has_ortho_tiles' => $detail->has_ortho_tiles,
                'ortho_tiles_url' => $detail->ortho_tiles_url,
                'ortho_min_zoom' => $detail->ortho_min_zoom,
                'ortho_max_zoom' => $detail->ortho_max_zoom,
            ];
        }

        if ($layer->layer_type === LayerType::VECTOR && ($detail = $layer->vectorDetail)) {
            $item['vector_detail'] = [
                'geometry_type' => $detail->geometry_type,
                'feature_count' => $detail->feature_count,
                'native_bounding_box' => $detail->native_bounding_box,
                'acquisition_date' => $detail->acquisition_date?->format('d M Y') ?? 'N/A',
            ];
        }

        return $item;
    }
}
