@props(['count' => 3])

<div {{ $attributes->merge(['class' => 'space-y-2.5']) }}>
    @for ($i = 0; $i < $count; $i++)
        <div class="animate-pulse rounded-xl border border-border/40 bg-neutral/30 p-2.5">
            <div class="flex items-start gap-2.5">
                <div class="h-8 w-8 shrink-0 rounded-lg bg-foreground/5"></div>
                <div class="min-w-0 flex-1 space-y-2">
                    <div class="h-3 w-3/4 rounded bg-foreground/10"></div>
                    <div class="space-y-1">
                        <div class="h-2 w-1/2 rounded bg-foreground/5"></div>
                        <div class="h-2 w-1/3 rounded bg-foreground/5"></div>
                    </div>
                </div>
            </div>
        </div>
    @endfor
</div>
