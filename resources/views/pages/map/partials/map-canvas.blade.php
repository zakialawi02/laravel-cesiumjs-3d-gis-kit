<!-- Feature Properties Panel -->
<div id="featurePropertiesPanel"
    class="absolute top-3 right-14 md:right-16 z-20 w-56 md:w-80 translate-x-[150%] rounded-xl bg-neutral/95 shadow-2xl backdrop-blur-md border border-border transition-transform duration-300 pointer-events-auto flex flex-col max-h-[70vh]">
    <div class="flex items-center justify-between border-b border-border px-3 py-2 md:px-4 md:py-3 shrink-0">
        <h3 class="text-xs md:text-sm font-bold text-primary flex items-center gap-2">
            <i class="ri-information-line"></i> Feature Properties
        </h3>
        <button onclick="window.mapController?.hideFeatureProperties()" class="text-foreground/60 hover:text-error transition-colors">
            <i class="ri-close-line text-lg"></i>
        </button>
    </div>
    <div class="overflow-y-auto p-2 [scrollbar-width:thin] flex-1">
        <div id="featurePropertiesContent" class="space-y-2">
            <!-- Properties will be injected here -->
        </div>
    </div>
</div>

<!-- Picked Coordinate Popup -->
<div id="coordinatePickerPopup"
    class="hidden absolute z-50 bg-neutral/95 shadow-2xl rounded-xl border border-border p-2 flex flex-col gap-1 bottom-2 left-2 right-3 md:bottom-auto md:top-2 md:right-16 md:left-auto md:w-64 backdrop-blur-md">
    <div class="flex items-center justify-between border-b border-border pb-1.5">
        <h3 class="font-bold text-xs text-primary flex items-center gap-1.5">
            <i class="ri-cursor-hand-line text-sm"></i>
            <span>Coordinate Info</span>
        </h3>
        <button id="closeCoordinatePickerPopupBtn" class="text-foreground/60 hover:text-error transition">
            <i class="ri-close-line text-lg"></i>
        </button>
    </div>

    <div class="flex flex-col gap-2">
        <!-- WGS Group -->
        <div id="pickWgsGroup" class="grid grid-cols-2 gap-1.5">
            <div class="bg-muted px-2 py-1 rounded flex flex-col">
                <span class="text-[8px] font-bold text-foreground/50 uppercase">Latitude (lat)</span>
                <span id="pickLatVal" class="text-[11px] font-mono font-bold text-foreground mt-0.5">-</span>
            </div>
            <div class="bg-muted px-2 py-1 rounded flex flex-col">
                <span class="text-[8px] font-bold text-foreground/50 uppercase">Longitude (lon)</span>
                <span id="pickLngVal" class="text-[11px] font-mono font-bold text-foreground mt-0.5">-</span>
            </div>
        </div>

        <!-- UTM Group -->
        <div id="pickUtmGroup" class="hidden grid grid-cols-2 gap-1.5">
            <div class="bg-muted px-2 py-1 rounded flex flex-col">
                <span class="text-[8px] font-bold text-foreground/50 uppercase">UTM X (Easting)</span>
                <span id="pickUtmXVal" class="text-[11px] font-mono font-bold text-foreground mt-0.5">-</span>
            </div>
            <div class="bg-muted px-2 py-1 rounded flex flex-col">
                <span class="text-[8px] font-bold text-foreground/50 uppercase">UTM Y (Northing)</span>
                <span id="pickUtmYVal" class="text-[11px] font-mono font-bold text-foreground mt-0.5">-</span>
            </div>
        </div>

        <!-- Height (Z) -->
        <div class="bg-primary/10 border border-primary/20 px-2 py-1.5 rounded flex items-center justify-between">
            <div class="flex flex-col">
                <span class="text-[8px] font-bold text-primary uppercase">Height (Z) / Elevation</span>
                <span id="pickZVal" class="text-xs font-mono font-bold text-primary mt-0.5">-</span>
            </div>
            <i class="ri-landscape-line text-primary text-base opacity-85"></i>
        </div>
    </div>
</div>

<!-- Map Container -->
<div id="cesiumContainer" class="flex-1 relative transition-all duration-300">
</div>

<!-- Coordinate Overlay -->
<div id="coordinateOverlay"
    class="absolute bottom-5 left-1/2 -translate-x-1/2 z-10 hidden md:flex items-center justify-center gap-4 rounded-full px-6 py-2 text-sm font-medium text-white shadow-2xl backdrop-blur-md border border-white/10 transition-all duration-300 opacity-0 pointer-events-none w-[440px] whitespace-nowrap"
    style="background-color: rgba(23, 23, 23, 0.75);">
    <div id="coordWgsGroup" class="flex items-center gap-4">
        <div class="flex items-center gap-2">
            <span class="text-white/60">Latitude (lat):</span>
            <span id="latValue" class="font-bold font-mono">-</span>
        </div>
        <div class="h-3 w-px bg-white/20"></div>
        <div class="flex items-center gap-2">
            <span class="text-white/60">Longitude (lon):</span>
            <span id="longValue" class="font-bold font-mono">-</span>
        </div>
    </div>
    <div id="coordUtmGroup" class="hidden items-center gap-4">
        <div class="flex items-center gap-2">
            <span class="text-white/60">X:</span>
            <span id="xValue" class="font-bold font-mono">-</span>
        </div>
        <div class="h-3 w-px bg-white/20"></div>
        <div class="flex items-center gap-2">
            <span class="text-white/60">Y:</span>
            <span id="yValue" class="font-bold font-mono">-</span>
        </div>
    </div>
</div>

<!-- Map Control Button -->
<div class="absolute right-2 top-2 z-10 flex flex-col gap-2">
    <!-- Compass / North Reset + Top View (Google Maps style) -->
    <button id="compassBtn"
        class="bg-neutral hover:bg-muted border border-border shadow-sm flex h-10 w-10 md:h-12 md:w-12 items-center justify-center rounded-xl text-foreground transition group relative"
        title="Reset North / Top View" aria-label="Reset North / Top View">
        <div id="compassIcon" class="transition-transform duration-200">
            <svg viewBox="0 0 24 24" class="w-5 h-5 md:w-6 md:h-6" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10" class="stroke-foreground/30" />
                <polygon points="12,2 14,10 12,8 10,10" class="fill-red-500 stroke-red-500" stroke-width="1" />
                <polygon points="12,22 10,14 12,16 14,14" class="fill-foreground/40 stroke-foreground/40" stroke-width="1" />
            </svg>
        </div>
    </button>

    <div class="h-px bg-border my-1"></div>

    <button id="zoomInBtn" class="bg-neutral hover:bg-muted border border-border shadow-sm flex h-10 w-10 md:h-12 md:w-12 items-center justify-center rounded-xl text-foreground transition"
        aria-label="Zoom In">
        <i class="ri-add-line text-lg"></i>
    </button>
    <button id="zoomOutBtn" class="bg-neutral hover:bg-muted border border-border shadow-sm flex h-10 w-10 md:h-12 md:w-12 items-center justify-center rounded-xl text-foreground transition"
        title="Zoom Out" aria-label="Zoom Out">
        <i class="ri-subtract-line text-lg"></i>
    </button>

    <div class="h-px bg-border my-1"></div>

    <!-- Coordinate Picker (on/off) -->
    <button id="coordinatePickerBtn" class="bg-neutral hover:bg-muted border border-border shadow-sm flex h-10 w-10 md:h-12 md:w-12 items-center justify-center rounded-xl text-foreground transition"
        aria-label="Check Coordinates" title="Check Coordinates">
        <i class="ri-sip-line text-lg"></i>
    </button>
    <!-- Pivot cam -->
    <button id="pivotCamBtn" class="bg-neutral hover:bg-muted border border-border shadow-sm flex h-10 w-10 md:h-12 md:w-12 items-center justify-center rounded-xl text-foreground transition"
        aria-label="Pivot Camera" title="Pivot Camera">
        <i class="ri-webcam-line text-lg"></i>
    </button>
    <!-- Geolocation (simple on/off) -->
    <button id="geolocationBtn" class="bg-neutral hover:bg-muted border border-border shadow-sm flex h-10 w-10 md:h-12 md:w-12 items-center justify-center rounded-xl text-foreground transition"
        aria-label="Geolocation" title="Geolocation (My location)">
        <i class="ri-navigation-line text-lg"></i>
    </button>

    <div class="h-px bg-border my-1"></div>

    <!-- Measure tools -->
    <div class="relative">
        <button id="measureToggleBtn" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="measurePanel"
            class="bg-neutral hover:bg-muted border border-border shadow-sm flex h-10 w-10 md:h-12 md:w-12 items-center justify-center rounded-xl text-foreground transition"
            title="Measure" aria-label="Measure tools">
            <i class="ri-ruler-2-line text-lg"></i>
        </button>
        <div id="measurePanel" role="toolbar" aria-label="Measure tools"
            class="absolute right-[calc(100%+0.5rem)] top-0 flex w-max origin-right scale-95 items-center gap-1.5 rounded-xl border border-border bg-neutral/95 p-1.5 opacity-0 shadow-md backdrop-blur-md transition-all duration-200 pointer-events-none">
            @foreach ([
                ['id' => 'measureDistanceHorizBtn', 'icon' => 'measure-distance-planar', 'label' => 'Planar', 'title' => 'Planar distance'],
                ['id' => 'measureDistanceSurfBtn', 'icon' => 'measure-distance-surface', 'label' => 'Surface', 'title' => 'Surface distance (follows terrain)'],
                ['id' => 'measureAreaHorizBtn', 'icon' => 'measure-area-planar', 'label' => 'Planar', 'title' => 'Planar area'],
                ['id' => 'measureAreaSurfBtn', 'icon' => 'measure-area-surface', 'label' => 'Surface', 'title' => 'Surface area (follows terrain)'],
                ['id' => 'measureCircleBtn', 'icon' => null, 'label' => 'Circle', 'title' => 'Circle radius & area'],
                ['id' => 'measureAnalysisBtn', 'icon' => 'measure-analysis', 'label' => 'Vol/Profile', 'title' => 'Cut & fill volume and elevation profile'],
            ] as $tool)
                <button id="{{ $tool['id'] }}" type="button" title="{{ $tool['title'] }}" aria-label="{{ $tool['title'] }}"
                    class="bg-neutral hover:bg-muted hover:text-primary border border-border flex h-11 w-12 md:h-12 md:w-14 flex-col items-center justify-center rounded-lg text-foreground transition">
                    @if ($tool['icon'])
                        <img src="{{ asset('assets/img/icons/'.$tool['icon'].'.svg') }}" alt="" class="h-4 w-4 md:h-5 md:w-5 dark:invert">
                    @else
                        <svg viewBox="0 0 24 24" class="h-4 w-4 md:h-5 md:w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" />
                            <line x1="12" y1="12" x2="18.36" y2="5.64" />
                            <circle cx="12" cy="12" r="1.2" fill="currentColor" />
                        </svg>
                    @endif
                    <span class="mt-0.5 text-[8px] leading-none opacity-80 md:text-[9px]">{{ $tool['label'] }}</span>
                </button>
            @endforeach
        </div>
    </div>

    <!-- Help -->
    <button id="helpBtn" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="cesiumHelpPanel"
        class="bg-neutral hover:bg-muted border border-border shadow-sm flex h-10 w-10 md:h-12 md:w-12 items-center justify-center rounded-xl text-foreground transition"
        title="Help" aria-label="Help">
        <i class="ri-question-line text-lg"></i>
    </button>
</div>

<!-- Drawing control bar (shown while a measure or analysis tool is drawing) -->
<div id="drawingControlBar" class="drawing-control-bar hidden" role="status" aria-live="polite">
    <div class="flex items-center gap-2 whitespace-nowrap text-xs font-bold text-primary">
        <i class="ri-edit-box-line animate-pulse text-sm"></i>
        <span id="drawingControlBarText">Drawing...</span>
    </div>
    <div class="flex items-center gap-1.5">
        <button id="drawingControlFinishPartBtn" type="button" class="hidden"></button>
        <button id="drawingControlDoneBtn" type="button" class="whitespace-nowrap rounded-md bg-success px-2.5 py-1 text-[11px] font-bold text-white shadow-sm transition hover:bg-success/90">
            Done
        </button>
        <button id="drawingControlUndoBtn" type="button" class="hidden whitespace-nowrap rounded-md bg-warning px-2.5 py-1 text-[11px] font-bold text-secondary-foreground shadow-sm transition hover:bg-warning/90">
            Undo
        </button>
        <button id="drawingControlCancelBtn" type="button" class="whitespace-nowrap rounded-md bg-error px-2.5 py-1 text-[11px] font-bold text-white shadow-sm transition hover:bg-error/90">
            Cancel
        </button>
    </div>
</div>

@include('pages.map.partials.panels.help-panel')
@include('pages.map.partials.panels.analysis-panel')
