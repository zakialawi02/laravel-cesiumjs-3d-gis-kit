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
    'actionDisabledClass' => '',
    'categoryColor' => null,
])

@php
    // This component is rendered inside a JS template literal (pages/map/index.blade.php):
    // props arrive as `${expression}` strings and are evaluated in the browser.
    $isActiveVar = str_replace(['${', '}'], '', $isActiveJS);
    $layerTypeVar = str_replace(['${', '}'], '', $layerType);

    $jsIconTernary =
        "{$layerTypeVar} === 'terrain' ? 'ri-landscape-line' : " .
        "({$layerTypeVar} === 'vector' ? 'ri-shape-line' : " .
        "({$layerTypeVar} === 'imagery' ? 'ri-image-line' : " .
        "({$layerTypeVar} === 'pointcloud' ? 'ri-bubble-chart-line' : 'ri-file-line')))";
@endphp

{{--
    Hooks used by cesium-map.js: .layer-card-item + data-* attributes, .toggle-{type}-btn (its first <i> is
    swapped for eye / eye-off / loader), .focus-{type}-btn, .configure-vector-btn, .vector-settings-panel
    and .label-field-select. The card's "visible" look follows the eye icon (see .map-layer in app.css).
--}}
<div class="layer-card-item map-layer {!! $opacityClass !!}" {!! $dataAttrs !!}>
    <div class="map-layer-row">
        <span class="map-layer-type map-layer-type--{!! $layerType !!}" aria-hidden="true">
            <i class="${ {!! $jsIconTernary !!} }"></i>
        </span>

        <div class="map-layer-main">
            <h3 class="map-layer-name" title="{!! $name !!}">{!! $name !!}</h3>
            <p class="map-layer-meta">
                <span class="map-layer-meta-key">Acquired</span>
                <span>{!! $acquisitionDate !!}</span>
            </p>
            <p class="map-layer-meta">
                <span class="map-layer-meta-key">Uploaded</span>
                <span>{!! $createdAt !!}</span>
                <span aria-hidden="true">&middot;</span>
                <span class="map-layer-id" title="ID: {!! $id !!}">{!! $id !!}</span>
            </p>
            {!! $badges !!}
        </div>

        <div class="map-layer-actions">
            <button type="button"
                class="configure-vector-btn map-icon-btn {!! $actionDisabledClass !!} ${ {!! $layerTypeVar !!} === 'vector' ? ({!! $isActiveVar !!} ? '' : 'hidden') : 'hidden' }"
                title="Label settings" aria-label="Label settings" data-layer-id="{!! $id !!}">
                <i class="ri-price-tag-3-line text-[15px]" aria-hidden="true"></i>
            </button>
            <button type="button" class="toggle-{!! $layerType !!}-btn map-icon-btn map-layer-toggle {!! $actionDisabledClass !!}" title="Show / hide" aria-label="Show or hide layer">
                <i class="${ {!! $isActiveVar !!} ? 'ri-eye-line text-primary' : 'ri-eye-off-line text-foreground/40' } text-[15px]"></i>
            </button>
            <button type="button" class="focus-{!! $layerType !!}-btn map-icon-btn {!! $actionDisabledClass !!}" title="Zoom to layer" aria-label="Zoom to layer">
                <i class="ri-focus-3-line text-[15px]" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div class="vector-settings-panel map-layer-settings hidden ${ {!! $layerTypeVar !!} === 'vector' ? '' : '!hidden' }">
        <span class="map-label">Label field</span>
        <select class="label-field-select map-field" aria-label="Label field">
            <option value="">No label</option>
        </select>
    </div>
</div>
