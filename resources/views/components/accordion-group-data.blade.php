@props(['id', 'title' => '', 'count' => null])

{{-- Vanilla accordion: toggled by ui-interactions.js (event delegation on [data-accordion-toggle]). --}}
<div class="accordion-group is-open border border-border/80 bg-neutral/50 rounded-lg overflow-hidden" data-accordion id="accordion-group-{{ $id }}-heading">
    <div class="flex items-center justify-between w-full px-1.5 py-1 bg-neutral/30 hover:bg-muted/40 transition">
        <button type="button" data-accordion-toggle class="accordion-toggle flex items-center gap-1.5 flex-1 min-w-0 text-left text-xs font-semibold text-foreground/85 focus:outline-none"
            aria-expanded="true" aria-controls="accordion-group-{{ $id }}-content">
            <i class="accordion-chevron ri-arrow-right-s-line transition-transform duration-200 flex-none text-foreground/60"></i>

            @if (isset($icon))
                {{ $icon }}
            @endif

            <span class="accordion-title truncate">{{ $title }}@if ($count !== null && $count !== '')
                    ({{ $count }})
                @endif
            </span>
        </button>

        @if (isset($actions))
            {{ $actions }}
        @endif
    </div>
    <div id="accordion-group-{{ $id }}-content" data-accordion-content class="w-full" aria-labelledby="accordion-group-{{ $id }}-heading">
        <div class="p-1 space-y-1.5">
            {{ $slot }}
        </div>
    </div>
</div>
