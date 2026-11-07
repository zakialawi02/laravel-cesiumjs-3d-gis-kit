{{-- Layers panel: floating card under the command bar (md+), bottom sheet on phones. --}}
<section id="layersPanel" class="map-panel map-sheet" aria-labelledby="layersPanelTitle">
    <span class="map-sheet-grabber" aria-hidden="true"></span>

    <header class="map-panel-head">
        <div class="min-w-0 flex-1">
            <h2 id="layersPanelTitle" class="map-panel-title">{{ __('Layers') }}</h2>
            <p class="map-panel-sub">{{ __('Show terrain, orthophoto and vector data on the globe.') }}</p>
        </div>
        <x-button-close id="closeLayersBtn" aria-label="{{ __('Close layers panel') }}" />
    </header>

    <div class="flex flex-none flex-col gap-2 px-3 pb-3">
        <label class="relative block">
            <span class="sr-only">{{ __('Search layers') }}</span>
            <i class="ri-search-line map-field-icon" aria-hidden="true"></i>
            <input type="search" id="searchLayers" placeholder="{{ __('Search layers') }}" autocomplete="off" spellcheck="false" class="map-field pl-9">
        </label>

        <div class="flex items-center gap-2">
            <div class="relative min-w-0 flex-1">
                <i class="ri-calendar-line map-field-icon" aria-hidden="true"></i>
                <input type="month" id="filterMonthYearLayers" value="" aria-label="{{ __('Filter by acquisition or upload month') }}" class="map-field cursor-pointer pl-9 pr-10">
                <button type="button" id="clearMonthYearLayersFilter" class="map-icon-btn absolute right-1 top-1/2 hidden size-8 -translate-y-1/2 hover:text-map-danger"
                    aria-label="{{ __('Clear month filter') }}">
                    <i class="ri-close-line" aria-hidden="true"></i>
                </button>
            </div>
            <button type="button" id="refreshLayersBtn" class="map-icon-btn map-icon-btn--boxed" aria-label="{{ __('Refresh layers') }}" data-tip="{{ __('Refresh layers') }}">
                <i class="ri-refresh-line" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div class="map-panel-body">
        <div id="allLayersList">
            <x-skeleton count="4" id="layersLoading" />
        </div>
    </div>
</section>
