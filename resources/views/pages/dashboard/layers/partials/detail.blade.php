@php
    use App\Enums\LayerType;

    $isTerrain = $layer->layer_type === LayerType::TERRAIN;
    $bbox = $detail?->native_bounding_box ?? [];
    $log = $layer->job?->logs->first();
    $rows = array_filter([
        'Type' => ucfirst($layer->layer_type->value),
        'Status' => str_replace('_', ' ', $layer->status->value),
        'Category' => $layer->category?->name ?? 'Uncategorized',
        'Uploaded by' => $layer->user?->name ?? 'System',
        'Uploaded' => $layer->created_at?->format('d M Y H:i'),
        'Published' => $layer->published_at?->format('d M Y H:i'),
        'Acquisition date' => $detail?->acquisition_date?->format('d M Y'),
        'Source file' => $layer->job?->sourceFile?->original_name,
        'Orthophoto' => $layer->job?->orthoFile?->original_name,
        'Total size' => $layer->formatted_size,
        'Source CRS' => $detail?->srs ?? ($detail?->source_epsg ? 'EPSG:'.$detail->source_epsg : null),
        'Geometry type' => $isTerrain ? null : $detail?->geometry_type,
        'Features' => $isTerrain ? null : ($detail ? number_format($detail->feature_count) : null),
        'Orthophoto tiles' => $isTerrain ? ($detail?->has_ortho_tiles ? 'Available (zoom '.$detail->ortho_min_zoom.'–'.$detail->ortho_max_zoom.')' : 'None') : null,
        'Conversion' => $layer->job ? $layer->job->status->label().($layer->job->conversion_tool ? ' • '.$layer->job->conversion_tool : '') : null,
    ], fn ($value) => $value !== null && $value !== '');
@endphp

<div class="space-y-5">
    <div>
        <p class="text-foreground text-lg font-bold">{{ $layer->name }}</p>
        <p class="text-foreground/50 font-mono text-[11px]">{{ $layer->id }}</p>
    </div>

    <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
        @foreach ($rows as $label => $value)
            <div class="border-border/60 border-b pb-2">
                <dt class="text-foreground/50 text-[10px] font-bold uppercase tracking-wider">{{ $label }}</dt>
                <dd class="text-foreground break-words text-sm">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    @if (isset($bbox['west'], $bbox['south'], $bbox['east'], $bbox['north']))
        <div>
            <p class="text-foreground/50 mb-1 text-[10px] font-bold uppercase tracking-wider">Bounding box (WGS84)</p>
            <p class="bg-muted text-foreground/80 rounded-lg px-3 py-2 font-mono text-xs">
                W {{ number_format((float) $bbox['west'], 6) }} · S {{ number_format((float) $bbox['south'], 6) }} · E {{ number_format((float) $bbox['east'], 6) }} · N {{ number_format((float) $bbox['north'], 6) }}
            </p>
        </div>
    @endif

    @if ($layer->job?->error_message)
        <div class="border-error/30 bg-error/5 rounded-lg border p-3">
            <p class="text-error mb-1 text-[10px] font-bold uppercase tracking-wider">Last conversion error</p>
            <pre class="text-foreground/80 max-h-40 overflow-auto whitespace-pre-wrap break-words text-xs">{{ \Illuminate\Support\Str::limit($layer->job->error_message, 2000) }}</pre>
        </div>
    @endif

    @if ($log)
        <p class="text-foreground/50 text-xs">
            Last run: attempt {{ $log->attempt }} • started {{ $log->started_at?->format('d M Y H:i') }}
            @if ($log->finished_at) • finished {{ $log->finished_at->format('H:i') }} @endif
            @if ($log->exit_code !== null) • exit code {{ $log->exit_code }} @endif
        </p>
    @endif
</div>
