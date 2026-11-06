@php
    $mouse = [
        ['img' => 'MouseLeft.svg', 'color' => '#66ccff', 'title' => 'Pan view', 'lines' => ['Left click + drag']],
        ['img' => 'MouseRight.svg', 'color' => '#65fd00', 'title' => 'Zoom', 'lines' => ['Right click + drag, or', 'mouse wheel']],
        ['img' => 'MouseMiddle.svg', 'color' => '#ffd800', 'title' => 'Rotate view', 'lines' => ['Middle click + drag, or', 'Ctrl + left/right click + drag']],
    ];
    $touch = [
        ['img' => 'TouchDrag.svg', 'color' => '#66ccff', 'title' => 'Pan view', 'lines' => ['One-finger drag']],
        ['img' => 'TouchZoom.svg', 'color' => '#65fd00', 'title' => 'Zoom', 'lines' => ['Two-finger pinch']],
        ['img' => 'TouchTilt.svg', 'color' => '#ffd800', 'title' => 'Tilt view', 'lines' => ['Two-finger drag, same direction']],
        ['img' => 'TouchRotate.svg', 'color' => '#d800d8', 'title' => 'Rotate view', 'lines' => ['Two-finger drag, opposite directions']],
    ];
    $shortcuts = [
        ['keys' => ['L'], 'label' => 'Layers panel'],
        ['keys' => ['D'], 'label' => 'Data panel (admin)'],
        ['keys' => ['Esc'], 'label' => 'Close panels and popovers'],
    ];
    $tools = [
        ['icon' => 'ri-ruler-2-line', 'label' => 'Measure distance, area, circle; Done or double-click to finish'],
        ['icon' => 'ri-bar-chart-box-line', 'label' => 'Vol/Profile: cut & fill volume and elevation profile (needs an active terrain)'],
        ['icon' => 'ri-sip-line', 'label' => 'Check coordinates of a clicked point'],
        ['icon' => 'ri-webcam-line', 'label' => 'Pivot the camera around a picked point'],
    ];
@endphp

<!-- Map help popover -->
<div id="cesiumHelpPanel" role="dialog" aria-label="Map help"
    class="absolute right-14 top-2 z-50 hidden w-72 overflow-hidden rounded-xl border border-white/10 text-white shadow-2xl backdrop-blur-md md:right-16"
    style="background-color: rgba(23, 23, 23, 0.9);">
    <div class="flex items-center justify-between border-b border-white/10 px-3 py-2">
        <span class="flex items-center gap-1.5 text-xs font-bold"><i class="ri-question-line"></i> Map Help</span>
        <button type="button" id="closeHelpPanelBtn" class="text-white/60 transition hover:text-white" aria-label="Close help">
            <i class="ri-close-line text-lg"></i>
        </button>
    </div>

    <div class="flex border-b border-white/10 text-xs font-semibold" role="tablist" aria-label="Navigation help">
        <button type="button" role="tab" id="helpMouseBtn" aria-controls="helpMouseContent" aria-selected="true" data-help-tab
            class="w-1/3 py-2 text-white/60 transition hover:text-white aria-selected:bg-white/10 aria-selected:text-white">Mouse</button>
        <button type="button" role="tab" id="helpTouchBtn" aria-controls="helpTouchContent" aria-selected="false" data-help-tab
            class="w-1/3 py-2 text-white/60 transition hover:text-white aria-selected:bg-white/10 aria-selected:text-white">Touch</button>
        <button type="button" role="tab" id="helpToolsBtn" aria-controls="helpToolsContent" aria-selected="false" data-help-tab
            class="w-1/3 py-2 text-white/60 transition hover:text-white aria-selected:bg-white/10 aria-selected:text-white">Tools</button>
    </div>

    <div id="helpMouseContent" role="tabpanel" aria-labelledby="helpMouseBtn" class="space-y-2 p-3">
        @foreach ($mouse as $item)
            <div class="flex items-start gap-3">
                <img src="{{ asset('cesium/Widgets/Images/NavigationHelp/'.$item['img']) }}" alt="" width="40" height="40" class="shrink-0">
                <div>
                    <div class="text-sm font-bold" style="color: {{ $item['color'] }}">{{ $item['title'] }}</div>
                    @foreach ($item['lines'] as $line)
                        <div class="text-xs text-white/80">{{ $line }}</div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div id="helpTouchContent" role="tabpanel" aria-labelledby="helpTouchBtn" class="hidden space-y-2 p-3">
        @foreach ($touch as $item)
            <div class="flex items-start gap-3">
                <img src="{{ asset('cesium/Widgets/Images/NavigationHelp/'.$item['img']) }}" alt="" width="56" height="40" class="shrink-0">
                <div>
                    <div class="text-sm font-bold" style="color: {{ $item['color'] }}">{{ $item['title'] }}</div>
                    @foreach ($item['lines'] as $line)
                        <div class="text-xs text-white/80">{{ $line }}</div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div id="helpToolsContent" role="tabpanel" aria-labelledby="helpToolsBtn" class="hidden space-y-3 p-3">
        <ul class="space-y-2">
            @foreach ($tools as $tool)
                <li class="flex items-start gap-2 text-xs text-white/80">
                    <i class="{{ $tool['icon'] }} mt-0.5 text-sm text-white"></i>
                    <span>{{ $tool['label'] }}</span>
                </li>
            @endforeach
        </ul>
        <div class="border-t border-white/10 pt-2">
            <div class="mb-1.5 text-[10px] font-bold uppercase tracking-wider text-white/50">Keyboard</div>
            <ul class="space-y-1">
                @foreach ($shortcuts as $shortcut)
                    <li class="flex items-center justify-between text-xs text-white/80">
                        <span>{{ $shortcut['label'] }}</span>
                        <span class="flex gap-1">
                            @foreach ($shortcut['keys'] as $key)
                                <kbd class="rounded border border-white/20 bg-white/10 px-1.5 py-0.5 font-mono text-[10px] text-white">{{ $key }}</kbd>
                            @endforeach
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
