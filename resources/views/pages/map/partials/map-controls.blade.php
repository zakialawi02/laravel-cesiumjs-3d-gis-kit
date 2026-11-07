@php
    $measureGroups = [
        __('Distance') => [
            ['id' => 'measureDistanceHorizBtn', 'glyph' => 'measure-distance-planar', 'label' => __('Planar'), 'title' => __('Planar distance')],
            ['id' => 'measureDistanceSurfBtn', 'glyph' => 'measure-distance-surface', 'label' => __('Surface'), 'title' => __('Surface distance (follows terrain)')],
        ],
        __('Area') => [
            ['id' => 'measureAreaHorizBtn', 'glyph' => 'measure-area-planar', 'label' => __('Planar'), 'title' => __('Planar area')],
            ['id' => 'measureAreaSurfBtn', 'glyph' => 'measure-area-surface', 'label' => __('Surface'), 'title' => __('Surface area (follows terrain)')],
        ],
        __('Shape & terrain') => [
            ['id' => 'measureCircleBtn', 'glyph' => null, 'label' => __('Circle'), 'title' => __('Circle radius and area')],
            ['id' => 'measureAnalysisBtn', 'glyph' => 'measure-analysis', 'label' => __('Vol / Profile'), 'title' => __('Cut & fill volume and elevation profile')],
        ],
    ];
@endphp

{{-- Map tools (top right). [data-map-tool] buttons get aria-pressed from applyBtnStyle() in cesium-map.js. --}}
<div class="map-rail map-rail--tools map-glass" role="group" aria-label="{{ __('Map tools') }}">
    <button type="button" id="coordinatePickerBtn" class="map-tool" data-map-tool aria-pressed="false" aria-label="{{ __('Pick coordinates') }}" data-tip="{{ __('Pick coordinates') }}">
        <i class="ri-sip-line" aria-hidden="true"></i>
    </button>
    <button type="button" id="pivotCamBtn" class="map-tool" data-map-tool aria-pressed="false" aria-label="{{ __('Pivot camera') }}" data-tip="{{ __('Orbit around a point') }}">
        <i class="ri-webcam-line" aria-hidden="true"></i>
    </button>
    <button type="button" id="geolocationBtn" class="map-tool" data-map-tool aria-pressed="false" aria-label="{{ __('My location') }}" data-tip="{{ __('My location') }}">
        <i class="ri-navigation-line" aria-hidden="true"></i>
    </button>

    <span class="map-rail-sep" aria-hidden="true"></span>

    <div class="map-measure relative">
        <button type="button" id="measureToggleBtn" class="map-tool" aria-haspopup="true" aria-expanded="false" aria-controls="measurePanel"
            aria-label="{{ __('Measure tools') }}" data-tip="{{ __('Measure') }}">
            <i class="ri-ruler-line" aria-hidden="true"></i>
        </button>

        <div id="measurePanel" class="map-flyout map-sheet pointer-events-none scale-95 opacity-0" role="group" aria-labelledby="measurePanelTitle">
            <p id="measurePanelTitle" class="map-flyout-title">{{ __('Measure') }}</p>
            @foreach ($measureGroups as $group => $tools)
                <p class="map-flyout-label">{{ $group }}</p>
                <div class="grid grid-cols-2 gap-1">
                    @foreach ($tools as $tool)
                        <button type="button" id="{{ $tool['id'] }}" class="map-flyout-tool" data-map-tool aria-pressed="false" aria-label="{{ $tool['title'] }}" title="{{ $tool['title'] }}">
                            @if ($tool['glyph'])
                                <span class="map-glyph" style="--glyph: url('{{ asset('assets/img/icons/'.$tool['glyph'].'.svg') }}')" aria-hidden="true"></span>
                            @else
                                <svg viewBox="0 0 24 24" class="size-[1.125rem] flex-none" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <circle cx="12" cy="12" r="9" />
                                    <line x1="12" y1="12" x2="18.36" y2="5.64" />
                                    <circle cx="12" cy="12" r="1.2" fill="currentColor" />
                                </svg>
                            @endif
                            <span>{{ $tool['label'] }}</span>
                        </button>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>

    <button type="button" id="helpBtn" class="map-tool" aria-haspopup="true" aria-expanded="false" aria-controls="cesiumHelpPanel" aria-label="{{ __('Map help') }}" data-tip="{{ __('Help') }}">
        <i class="ri-question-line" aria-hidden="true"></i>
    </button>
</div>

{{-- Camera navigation (bottom right) --}}
<div class="map-rail map-rail--nav map-glass" role="group" aria-label="{{ __('Camera') }}">
    <button type="button" id="compassBtn" class="map-tool map-compass" aria-label="{{ __('Reset north, then top view') }}" data-tip="{{ __('Reset north / top view') }}">
        <span id="compassIcon">
            <svg viewBox="0 0 24 24" class="size-6" aria-hidden="true">
                <circle cx="12" cy="12" r="9.5" fill="none" stroke="currentColor" stroke-opacity="0.3" stroke-width="1.5" />
                <path d="M12 3.6 14.9 12H9.1Z" fill="#e5484d" />
                <path d="M12 20.4 9.1 12h5.8Z" fill="currentColor" fill-opacity="0.5" />
                <circle cx="12" cy="12" r="1.4" style="fill: var(--map-surface-strong)" />
            </svg>
        </span>
    </button>

    <span class="map-rail-sep" aria-hidden="true"></span>

    <button type="button" id="zoomInBtn" class="map-tool" aria-label="{{ __('Zoom in') }}" data-tip="{{ __('Zoom in') }}">
        <i class="ri-add-line" aria-hidden="true"></i>
    </button>
    <button type="button" id="zoomOutBtn" class="map-tool" aria-label="{{ __('Zoom out') }}" data-tip="{{ __('Zoom out') }}">
        <i class="ri-subtract-line" aria-hidden="true"></i>
    </button>
</div>
