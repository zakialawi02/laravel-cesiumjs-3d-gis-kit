@props(['uploadId', 'fileName', 'type', 'typeIcon'])

{{-- In-flight upload, rendered from JS (pages/map/index.blade.php). Hooks: .progress-*, .upload-controls, .pause-resume-btn, .cancel-upload-btn. --}}
<div id="{!! $uploadId !!}" class="rounded-[0.875rem] border border-map-line bg-map-surface-strong p-3 transition-all">
    <div class="flex items-start gap-3">
        <span class="flex size-9 shrink-0 items-center justify-center rounded-[0.625rem] bg-map-accent-soft text-map-accent-ink" aria-hidden="true">
            <i class="{!! $typeIcon !!} text-lg"></i>
        </span>
        <div class="min-w-0 flex-1">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <h4 class="truncate text-[0.8125rem] font-semibold text-map-ink">{!! $fileName !!}</h4>
                    <p class="text-[0.6875rem] font-semibold uppercase tracking-wide text-map-subtle">{!! ucfirst($type) !!}</p>
                </div>
                <div class="upload-controls -mr-1 -mt-1 flex shrink-0 items-center">
                    <button type="button" class="pause-resume-btn map-icon-btn size-8" title="Pause upload" aria-label="Pause or resume upload">
                        <i class="ri-pause-line text-sm"></i>
                    </button>
                    <button type="button" class="cancel-upload-btn map-icon-btn size-8 hover:text-map-danger" title="Cancel upload" aria-label="Cancel upload">
                        <i class="ri-close-line text-sm"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="mt-2.5 flex flex-col gap-1.5">
        <div class="flex items-center justify-between gap-2 text-[0.6875rem]">
            <span class="progress-label min-w-0 flex-1 truncate font-medium italic text-foreground/70">Uploading...</span>
            <span class="progress-percent shrink-0 font-semibold tabular-nums text-map-accent-ink">0%</span>
        </div>
        <div class="progress-container h-1.5 w-full overflow-hidden rounded-full bg-map-hover">
            <div class="progress-bar h-full w-0 rounded-full bg-map-accent transition-[width] duration-300"></div>
        </div>
    </div>
</div>
