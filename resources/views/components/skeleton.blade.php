@props(['count' => 3])

{{-- Loading placeholder shaped like the map layer cards. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col gap-1.5', 'aria-hidden' => 'true']) }}>
    <div class="mb-1 h-4 w-32 animate-pulse rounded-md bg-map-hover"></div>
    @for ($i = 0; $i < $count; $i++)
        <div class="animate-pulse rounded-[0.875rem] border border-map-line p-2.5">
            <div class="flex items-start gap-3">
                <div class="size-9 shrink-0 rounded-[0.625rem] bg-map-hover"></div>
                <div class="min-w-0 flex-1 space-y-2 pt-1">
                    <div class="h-3 w-2/3 rounded bg-map-hover"></div>
                    <div class="h-2.5 w-1/2 rounded bg-map-hover"></div>
                    <div class="h-2.5 w-3/4 rounded bg-map-hover"></div>
                </div>
                <div class="flex gap-1.5 pt-1">
                    <div class="size-6 rounded-md bg-map-hover"></div>
                    <div class="size-6 rounded-md bg-map-hover"></div>
                </div>
            </div>
        </div>
    @endfor
</div>
