@php
    $mouse = [
        ['img' => 'MouseLeft.svg', 'color' => '#66ccff', 'title' => __('Pan view'), 'lines' => [__('Left click and drag')]],
        ['img' => 'MouseRight.svg', 'color' => '#65fd00', 'title' => __('Zoom'), 'lines' => [__('Right click and drag, or scroll the wheel')]],
        ['img' => 'MouseMiddle.svg', 'color' => '#ffd800', 'title' => __('Rotate view'), 'lines' => [__('Middle click and drag, or Ctrl + left/right click and drag')]],
    ];
    $touch = [
        ['img' => 'TouchDrag.svg', 'color' => '#66ccff', 'title' => __('Pan view'), 'lines' => [__('Drag with one finger')]],
        ['img' => 'TouchZoom.svg', 'color' => '#65fd00', 'title' => __('Zoom'), 'lines' => [__('Pinch with two fingers')]],
        ['img' => 'TouchTilt.svg', 'color' => '#ffd800', 'title' => __('Tilt view'), 'lines' => [__('Drag two fingers in the same direction')]],
        ['img' => 'TouchRotate.svg', 'color' => '#d800d8', 'title' => __('Rotate view'), 'lines' => [__('Drag two fingers in opposite directions')]],
    ];
    $tools = [
        ['icon' => 'ri-ruler-line', 'title' => __('Measure'), 'label' => __('Distance, area and circle. Press Done or double-click to finish a shape.')],
        ['icon' => 'ri-bar-chart-box-line', 'title' => __('Vol / Profile'), 'label' => __('Cut & fill volume and elevation profiles. Needs an active terrain layer.')],
        ['icon' => 'ri-sip-line', 'title' => __('Pick coordinates'), 'label' => __('Shows latitude, longitude and elevation of a clicked point.')],
        ['icon' => 'ri-webcam-line', 'title' => __('Pivot camera'), 'label' => __('Orbits the camera around a point you pick.')],
        ['icon' => 'ri-landscape-line', 'title' => __('Depth test against terrain'), 'label' => __('Hides vector features behind hills or below the terrain surface. Switch it in the account menu.')],
    ];
    $shortcuts = [
        ['keys' => ['L'], 'label' => __('Layers panel')],
        ['keys' => ['D'], 'label' => __('Data panel (admin)')],
        ['keys' => ['Esc'], 'label' => __('Close panels and popovers')],
    ];
@endphp

{{-- Map help popover (toggled by #helpBtn in cesium-map.js) --}}
<div id="cesiumHelpPanel" class="map-card map-card--help map-sheet hidden" role="dialog" aria-labelledby="cesiumHelpTitle">
    <header class="map-card-head">
        <span class="map-card-icon" aria-hidden="true"><i class="ri-question-line"></i></span>
        <h3 id="cesiumHelpTitle" class="map-card-title">{{ __('Map help') }}</h3>
        <button type="button" id="closeHelpPanelBtn" class="map-icon-btn" aria-label="{{ __('Close help') }}">
            <i class="ri-close-line" aria-hidden="true"></i>
        </button>
    </header>

    <div class="flex-none px-3 pb-2">
        <div class="map-segmented" role="tablist" aria-label="{{ __('Help topics') }}">
            <button type="button" role="tab" id="helpMouseBtn" aria-controls="helpMouseContent" aria-selected="true" class="map-segment">
                <i class="ri-mouse-line" aria-hidden="true"></i>{{ __('Mouse') }}
            </button>
            <button type="button" role="tab" id="helpTouchBtn" aria-controls="helpTouchContent" aria-selected="false" class="map-segment">
                <i class="ri-hand" aria-hidden="true"></i>{{ __('Touch') }}
            </button>
            <button type="button" role="tab" id="helpToolsBtn" aria-controls="helpToolsContent" aria-selected="false" class="map-segment">
                <i class="ri-tools-line" aria-hidden="true"></i>{{ __('Tools') }}
            </button>
        </div>
    </div>

    <div id="helpMouseContent" role="tabpanel" aria-labelledby="helpMouseBtn" class="map-card-body flex flex-col gap-1">
        @foreach ($mouse as $item)
            <div class="map-help-item">
                <span class="map-help-tile"><img src="{{ asset('cesium/Widgets/Images/NavigationHelp/'.$item['img']) }}" alt="" width="40" height="40"></span>
                <div class="min-w-0">
                    <p class="map-help-title"><span class="map-help-swatch" style="--swatch: {{ $item['color'] }}"></span>{{ $item['title'] }}</p>
                    @foreach ($item['lines'] as $line)
                        <p class="map-help-desc">{{ $line }}</p>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div id="helpTouchContent" role="tabpanel" aria-labelledby="helpTouchBtn" class="map-card-body flex hidden flex-col gap-1">
        @foreach ($touch as $item)
            <div class="map-help-item">
                <span class="map-help-tile"><img src="{{ asset('cesium/Widgets/Images/NavigationHelp/'.$item['img']) }}" alt="" width="48" height="34"></span>
                <div class="min-w-0">
                    <p class="map-help-title"><span class="map-help-swatch" style="--swatch: {{ $item['color'] }}"></span>{{ $item['title'] }}</p>
                    @foreach ($item['lines'] as $line)
                        <p class="map-help-desc">{{ $line }}</p>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div id="helpToolsContent" role="tabpanel" aria-labelledby="helpToolsBtn" class="map-card-body flex hidden flex-col gap-3">
        <ul class="flex flex-col gap-1">
            @foreach ($tools as $tool)
                <li class="map-help-item items-start">
                    <span class="map-card-icon mt-0.5" aria-hidden="true"><i class="{{ $tool['icon'] }}"></i></span>
                    <div class="min-w-0">
                        <p class="map-help-title">{{ $tool['title'] }}</p>
                        <p class="map-help-desc">{{ $tool['label'] }}</p>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="border-t border-map-line pt-3">
            <p class="map-label mb-2">{{ __('Keyboard') }}</p>
            <ul class="flex flex-col gap-1.5">
                @foreach ($shortcuts as $shortcut)
                    <li class="flex items-center justify-between gap-3 text-xs text-map-muted">
                        <span>{{ $shortcut['label'] }}</span>
                        <span class="flex gap-1">
                            @foreach ($shortcut['keys'] as $key)
                                <kbd class="map-kbd">{{ $key }}</kbd>
                            @endforeach
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
