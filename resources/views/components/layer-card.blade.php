@props([
    'id',
    'name',
    'acquisitionDate' => 'N/A',
    'createdAt' => 'N/A',
    'dataAttrs' => '',
    'badges' => '',
    'opacityClass' => '',
    'layerType',
    'isActiveJS',
    'actionDisabledClass' => 'hover:bg-primary/10 hover:text-primary',
    'categoryColor' => null,
])

@php
    // Strip '${' and '}' if the user passes the variable wrapped in JS template literal syntax
    // so we can use it safely inside our own JS conditionals.
    $isActiveVar = str_replace(['${', '}'], '', $isActiveJS);
    $layerTypeVar = str_replace(['${', '}'], '', $layerType);

    $jsColorTernary =
        "{$layerTypeVar} === 'terrain' ? 'text-amber-600 bg-amber-50 border-amber-100' : " .
        "({$layerTypeVar} === 'vector' ? 'text-blue-600 bg-blue-50 border-blue-100' : " .
        "({$layerTypeVar} === 'imagery' ? 'text-green-600 bg-green-50 border-green-100' : " .
        "({$layerTypeVar} === 'pointcloud' ? 'text-purple-600 bg-purple-50 border-purple-100' : 'text-gray-600 bg-gray-50 border-gray-100')))";

    $jsIconTernary =
        "{$layerTypeVar} === 'terrain' ? 'ri-landscape-line' : " .
        "({$layerTypeVar} === 'vector' ? 'ri-shape-line' : " .
        "({$layerTypeVar} === 'imagery' ? 'ri-image-line' : " .
        "({$layerTypeVar} === 'pointcloud' ? 'ri-bubble-chart-line' : 'ri-file-line')))";
@endphp

<div class="layer-card-item group rounded-xl border border-border/60 bg-neutral/50 p-2.5 transition-all hover:border-primary/30 hover:shadow-sm hover:shadow-primary/5 {!! $opacityClass !!}"
    {!! $dataAttrs !!}>
    <div class="flex items-start gap-2.5">
        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border ${ {!! $jsColorTernary !!} }">
            <i class="${ {!! $jsIconTernary !!} } text-base"></i>
        </div>
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-1.5">
                <h3 class="truncate text-[13px] font-semibold text-foreground leading-tight">{!! $name !!}</h3>
            </div>
            <div class="mt-1 space-y-0.5">
                <div class="flex items-center gap-1.5 text-[10px]">
                    <span class="w-16 font-bold text-foreground/40 uppercase tracking-tighter text-[8px]">Acquired</span>
                    <span class="text-foreground/60 leading-none">{!! $acquisitionDate !!}</span>
                </div>
                <div class="flex items-center gap-1.5 text-[10px]">
                    <span class="w-16 font-bold text-foreground/40 uppercase tracking-tighter text-[8px]">Uploaded</span>
                    <span class="text-foreground/60 leading-none">{!! $createdAt !!}</span>
                </div>
            </div>
        </div>
    </div>
    <div class="mt-2 flex items-center justify-between border-t border-border/40 pt-1 gap-1 min-w-0">
        <div class="flex items-center gap-1 min-w-0">
            <span class="rounded-md bg-foreground/5 px-1.5 py-0.5 font-mono text-[9px] font-medium text-foreground/40 ring-1 ring-inset ring-foreground/10 truncate max-w-[140px]"
                title="ID: {!! $id !!}">ID: {!! $id !!}</span>
            {!! $badges !!}
        </div>
        <div class="flex items-center gap-0.5 shrink-0">
            <button
                class="configure-vector-btn flex h-7 w-7 items-center justify-center rounded-md text-foreground/40 transition-all hover:bg-primary/10 hover:text-primary ${ {!! $layerTypeVar !!} === 'vector' ? ({!! $isActiveVar !!} ? '' : 'hidden') : 'hidden' }"
                title="Label Settings" data-layer-id="{!! $id !!}">
                <i class="ri-settings-4-line text-[15px]"></i>
            </button>


            <button class="toggle-{!! $layerType !!}-btn flex h-7 w-7 items-center justify-center rounded-md transition-all {!! $actionDisabledClass !!}" title="Show/Hide">
                <i class="${ {!! $isActiveVar !!} ? 'ri-eye-line text-primary' : 'ri-eye-off-line text-foreground/40' } text-[15px]"></i>
            </button>

            <button class="focus-{!! $layerType !!}-btn flex h-7 w-7 items-center justify-center rounded-md text-foreground/40 transition-all {!! $actionDisabledClass !!}" title="Zoom to Layer">
                <i class="ri-focus-3-line text-[15px]"></i>
            </button>

        </div>
    </div>
    <div class="vector-settings-panel hidden mt-2.5 border-t border-border/40 pt-2 text-xs ${ {!! $layerTypeVar !!} === 'vector' ? '' : '!hidden' }">
        <div class="flex items-center justify-between gap-2">
            <span class="text-[10px] font-bold text-foreground/50 uppercase tracking-tighter">Label Field</span>
            <select
                class="label-field-select rounded-md border border-border bg-input py-0.5 pl-1.5 pr-6 text-[11px] font-medium text-foreground/80 focus:ring-1 focus:ring-primary focus:border-primary cursor-pointer w-32 text-ellipsis overflow-hidden">
                <option value="">No Label</option>
            </select>
        </div>
    </div>
</div>
