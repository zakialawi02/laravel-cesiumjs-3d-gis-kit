<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\JobStatus;
use App\Enums\LayerStatus;
use App\Enums\LayerType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateLayerRequest;
use App\Models\Layer;
use App\Models\LayerCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LayerController extends Controller
{
    /**
     * Searchable, filterable list of every layer in any status.
     */
    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->string('search')),
            'type' => in_array($request->input('type'), array_column(LayerType::cases(), 'value'), true) ? $request->input('type') : null,
            'status' => in_array($request->input('status'), array_column(LayerStatus::cases(), 'value'), true) ? $request->input('status') : null,
            'category' => (string) $request->string('category'),
        ];

        $layers = Layer::query()
            ->with(['category:id,name,color', 'user:id,name', 'job:id,status,source_file_id,ortho_file_id', 'job.sourceFile:id,file_size,original_name', 'job.orthoFile:id,file_size', 'terrainDetail:id,layer_id,acquisition_date', 'vectorDetail:id,layer_id,acquisition_date'])
            ->when($filters['search'] !== '', fn ($query) => $query->whereLike('name', "%{$filters['search']}%"))
            ->when($filters['type'], fn ($query, $type) => $query->where('layer_type', $type))
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['category'] !== '', fn ($query) => $query->where('layer_category_id', $filters['category']))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $statusCounts = Layer::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('pages.dashboard.layers.index', [
            'layers' => $layers,
            'filters' => $filters,
            'categories' => LayerCategory::query()->orderBy('sort_order')->get(['id', 'name', 'color']),
            'statusCounts' => $statusCounts,
        ]);
    }

    /**
     * Detail panel (HTML fragment) for the "View" modal.
     */
    public function show(Layer $layer): View
    {
        $layer->load([
            'category:id,name,color',
            'user:id,name',
            'job.sourceFile',
            'job.orthoFile',
            'job.logs' => fn ($query) => $query->latest()->limit(1),
            'terrainDetail',
            'vectorDetail',
        ]);

        return view('pages.dashboard.layers.partials.detail', [
            'layer' => $layer,
            'detail' => $layer->layer_type === LayerType::TERRAIN ? $layer->terrainDetail : $layer->vectorDetail,
        ]);
    }

    /**
     * Rename, recategorise or re-date a layer.
     */
    public function update(UpdateLayerRequest $request, Layer $layer): RedirectResponse
    {
        DB::transaction(function () use ($request, $layer): void {
            $layer->update([
                'name' => $request->validated('name'),
                'layer_category_id' => $request->validated('layer_category_id') ?: LayerCategory::where('slug', 'other')->value('id'),
            ]);

            $detail = $layer->layer_type === LayerType::TERRAIN ? $layer->terrainDetail : $layer->vectorDetail;
            $detail?->update(['acquisition_date' => $request->validated('acquisition_date')]);
        });

        return back()->with('success', "Layer \"{$layer->name}\" updated.");
    }

    /**
     * Hide a published layer from the map without deleting any data.
     */
    public function archive(Layer $layer): RedirectResponse
    {
        abort_unless($layer->status === LayerStatus::PUBLISHED, 422, 'Only published layers can be archived.');

        $layer->update(['status' => LayerStatus::ARCHIVED]);

        return back()->with('success', "Layer \"{$layer->name}\" archived. It is hidden from the map.");
    }

    /**
     * Put an archived layer back on the map.
     */
    public function restore(Layer $layer): RedirectResponse
    {
        abort_unless($layer->status === LayerStatus::ARCHIVED, 422, 'Only archived layers can be restored.');
        abort_unless($layer->job?->status === JobStatus::DONE, 422, 'The layer has no completed conversion to restore.');

        $layer->update(['status' => LayerStatus::PUBLISHED]);

        return back()->with('success', "Layer \"{$layer->name}\" restored to the map.");
    }
}
