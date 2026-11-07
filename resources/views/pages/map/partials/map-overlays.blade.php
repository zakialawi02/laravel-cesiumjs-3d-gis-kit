{{-- Feature properties: filled by CesiumMapController.showFeatureProperties(), hidden with translate-x-[150%]. --}}
<aside id="featurePropertiesPanel" class="map-card map-card--feature map-sheet translate-x-[150%]" aria-labelledby="featurePropertiesTitle">
    <header class="map-card-head">
        <span class="map-card-icon" aria-hidden="true"><i class="ri-information-line"></i></span>
        <h3 id="featurePropertiesTitle" class="map-card-title">{{ __('Feature properties') }}</h3>
        <button type="button" class="map-icon-btn" onclick="window.mapController?.hideFeatureProperties()" aria-label="{{ __('Close feature properties') }}">
            <i class="ri-close-line" aria-hidden="true"></i>
        </button>
    </header>
    <div class="map-card-body">
        <div id="featurePropertiesContent"></div>
    </div>
</aside>

{{-- Picked point (coordinate picker mode) --}}
<aside id="coordinatePickerPopup" class="map-card map-card--point map-sheet hidden" aria-labelledby="coordinatePickerTitle">
    <header class="map-card-head">
        <span class="map-card-icon" aria-hidden="true"><i class="ri-sip-line"></i></span>
        <h3 id="coordinatePickerTitle" class="map-card-title">{{ __('Picked point') }}</h3>
        <button type="button" id="copyPickedCoordinatesBtn" class="map-icon-btn" aria-label="{{ __('Copy coordinates') }}" data-tip="{{ __('Copy') }}">
            <i class="ri-file-copy-line" aria-hidden="true"></i>
        </button>
        <button type="button" id="closeCoordinatePickerPopupBtn" class="map-icon-btn" aria-label="{{ __('Stop picking coordinates') }}" data-tip="{{ __('Stop picking') }}">
            <i class="ri-close-line" aria-hidden="true"></i>
        </button>
    </header>

    <div class="map-card-body" aria-live="polite">
        <div id="pickWgsGroup" class="grid grid-cols-2 gap-2">
            <div class="map-readout">
                <span class="map-readout-label">{{ __('Latitude') }}</span>
                <span id="pickLatVal" class="map-readout-value">-</span>
            </div>
            <div class="map-readout">
                <span class="map-readout-label">{{ __('Longitude') }}</span>
                <span id="pickLngVal" class="map-readout-value">-</span>
            </div>
        </div>

        <div id="pickUtmGroup" class="hidden grid-cols-2 gap-2">
            <div class="map-readout">
                <span class="map-readout-label">{{ __('UTM X (easting)') }}</span>
                <span id="pickUtmXVal" class="map-readout-value">-</span>
            </div>
            <div class="map-readout">
                <span class="map-readout-label">{{ __('UTM Y (northing)') }}</span>
                <span id="pickUtmYVal" class="map-readout-value">-</span>
            </div>
        </div>

        <div class="map-readout map-readout--accent mt-2">
            <span class="flex min-w-0 flex-col gap-0.5">
                <span class="map-readout-label">{{ __('Elevation (Z)') }}</span>
                <span id="pickZVal" class="map-readout-value">-</span>
            </span>
            <i class="ri-landscape-line text-lg" aria-hidden="true"></i>
        </div>

        <p class="map-card-note">{{ __('Click the map to pick another point. Clicks snap to nearby vector vertices.') }}</p>
    </div>
</aside>

{{-- Cursor coordinates (pointer devices, md+). Shown by initMousePosition() through opacity-0 / opacity-100. --}}
<div id="coordinateOverlay" class="map-hud map-glass opacity-0" aria-hidden="true">
    <i class="ri-focus-3-line text-sm text-map-subtle"></i>
    <div id="coordWgsGroup" class="flex items-center gap-3">
        <span class="map-hud-item"><span class="map-hud-key">Lat</span><span id="latValue" class="map-hud-value">-</span></span>
        <span class="map-hud-item"><span class="map-hud-key">Lon</span><span id="longValue" class="map-hud-value">-</span></span>
    </div>
    <div id="coordUtmGroup" class="hidden items-center gap-3">
        <span class="map-hud-item"><span class="map-hud-key">X</span><span id="xValue" class="map-hud-value">-</span></span>
        <span class="map-hud-item"><span class="map-hud-key">Y</span><span id="yValue" class="map-hud-value">-</span></span>
    </div>
    <span class="map-hud-tag">WGS84</span>
</div>

{{-- Drawing control bar (shown while a measure or analysis tool is drawing) --}}
<div id="drawingControlBar" class="map-drawbar map-glass hidden" role="status" aria-live="polite">
    <div class="map-drawbar-text">
        <span class="map-pulse" aria-hidden="true"></span>
        <span id="drawingControlBarText">{{ __('Drawing...') }}</span>
    </div>
    <button id="drawingControlFinishPartBtn" type="button" class="hidden"></button>
    <button id="drawingControlUndoBtn" type="button" class="map-btn map-btn-ghost hidden">
        <i class="ri-arrow-go-back-line" aria-hidden="true"></i>
        <span>{{ __('Undo') }}</span>
    </button>
    <button id="drawingControlCancelBtn" type="button" class="map-btn map-btn-ghost">{{ __('Cancel') }}</button>
    <button id="drawingControlDoneBtn" type="button" class="map-btn map-btn-primary">
        <i class="ri-check-line" aria-hidden="true"></i>
        <span>{{ __('Done') }}</span>
    </button>
</div>
