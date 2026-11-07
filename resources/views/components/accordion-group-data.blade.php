@props(['id', 'title' => '', 'count' => null])

{{-- Vanilla accordion: toggled by ui-interactions.js (event delegation on [data-accordion-toggle]). --}}
<div class="accordion-group is-open map-group" data-accordion id="accordion-group-{{ $id }}-heading">
    <div class="flex items-center gap-1">
        <button type="button" data-accordion-toggle class="accordion-toggle map-group-toggle" aria-expanded="true" aria-controls="accordion-group-{{ $id }}-content">
            <i class="accordion-chevron ri-arrow-right-s-line" aria-hidden="true"></i>

            @if (isset($icon))
                {{ $icon }}
            @endif

            <span class="accordion-title min-w-0 truncate">{{ $title }}</span>

            @if ($count !== null && $count !== '')
                <span class="map-group-count">{{ $count }}</span>
            @endif
        </button>

        @if (isset($actions))
            {{ $actions }}
        @endif
    </div>
    <div id="accordion-group-{{ $id }}-content" data-accordion-content class="map-group-body" aria-labelledby="accordion-group-{{ $id }}-heading">
        {{ $slot }}
    </div>
</div>
