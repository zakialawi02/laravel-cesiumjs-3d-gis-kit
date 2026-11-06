@props(['uploadId', 'fileName', 'type', 'typeIcon'])

<div id="{!! $uploadId !!}" class="group relative overflow-hidden rounded-xl bg-neutral border border-border p-3 shadow-sm transition-all">
    <div class="relative z-10">
        <div class="mb-3 flex items-start gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <i class="{!! $typeIcon !!} text-xl"></i>
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between">
                    <h4 class="text-[11px] font-bold text-foreground truncate max-w-[120px]">{!! $fileName !!}</h4>
                    <div class="flex items-center gap-1.5 upload-controls">
                        <button class="pause-resume-btn p-1 rounded-md text-foreground/40 hover:bg-primary/10 hover:text-primary transition-all" title="Pause/Resume">
                            <i class="ri-pause-line text-sm"></i>
                        </button>
                        <button class="cancel-upload-btn p-1 rounded-md text-foreground/40 hover:bg-error/10 hover:text-error transition-all" title="Cancel Upload">
                            <i class="ri-close-line text-sm"></i>
                        </button>
                    </div>
                </div>
                <div class="flex items-center gap-2 mt-0.5">
                    <span class="text-[11px] font-bold text-primary bg-primary/10 px-1.5 py-0.5 rounded uppercase tracking-wider">{!! ucfirst($type) !!}</span>
                </div>
            </div>
        </div>
        <div class="space-y-1.5">
            <div class="flex items-center justify-between text-[10px] gap-2">
                <span class="font-medium text-foreground/70 italic progress-label truncate min-w-0 flex-1">Uploading...</span>
                <span class="font-bold text-primary progress-percent shrink-0">0%</span>
            </div>
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-foreground/5 p-[1px] progress-container">
                <div class="h-full w-0 rounded-full bg-gradient-to-r from-primary to-secondary shadow-[0_0_10px_rgba(var(--color-primary),0.3)] transition-all duration-300 progress-bar"></div>
            </div>
        </div>
    </div>
</div>
