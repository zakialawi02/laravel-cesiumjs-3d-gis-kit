{{-- Data panel (admins): upload entry point, in-flight uploads and recent conversions. --}}
<section id="dataPanel" class="map-panel map-sheet" aria-labelledby="dataPanelTitle">
    <span class="map-sheet-grabber" aria-hidden="true"></span>

    <header class="map-panel-head">
        <div class="min-w-0 flex-1">
            <h2 id="dataPanelTitle" class="map-panel-title">{{ __('Data') }}</h2>
            <p class="map-panel-sub">{{ __('Upload elevation models, orthophotos and vector files.') }}</p>
        </div>
        <x-button-close id="closeDataBtn" aria-label="{{ __('Close data panel') }}" />
    </header>

    <div class="flex-none px-3 pb-3">
        <button type="button" id="openUploadModalBtn" class="map-btn map-btn-primary w-full" data-modal-open="upload">
            <i class="ri-upload-cloud-2-line" aria-hidden="true"></i>
            <span>{{ __('Upload data') }}</span>
        </button>
    </div>

    <div class="map-panel-body flex flex-col gap-5">
        <section id="activeUploadsSection" aria-labelledby="activeUploadsTitle">
            <h3 id="activeUploadsTitle" class="map-label mb-2">{{ __('In progress') }}</h3>
            <div id="activeUploadsList" class="flex flex-col gap-2"></div>

            {{-- Toggled by syncUploadBadge() in pages/map/index.blade.php --}}
            <div id="activeUploadsEmpty" class="map-empty">
                <i class="ri-upload-cloud-2-line" aria-hidden="true"></i>
                <p class="map-empty-title">{{ __('No uploads in progress') }}</p>
                <p class="map-empty-text">{{ __('Uploads and conversions appear here until the layer is ready.') }}</p>
            </div>
        </section>

        <section aria-labelledby="recentUploadsTitle">
            <div class="mb-1.5 flex items-center justify-between gap-2">
                <h3 id="recentUploadsTitle" class="map-label">{{ __('Recent uploads') }}</h3>
                <button type="button" id="refreshRecentUploadsBtn" class="map-link-btn">
                    <i class="ri-refresh-line" aria-hidden="true"></i>
                    <span>{{ __('Refresh') }}</span>
                </button>
            </div>
            <div id="recentUploadList" class="flex flex-col gap-1.5">
                <x-loading-spinner id="recentUploadLoading" message="Loading recent uploads" />
            </div>
        </section>
    </div>
</section>
