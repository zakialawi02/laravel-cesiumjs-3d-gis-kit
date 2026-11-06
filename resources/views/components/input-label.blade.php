@props(['value', 'icon' => null])

<label {{ $attributes->merge(['class' => 'flex items-center gap-2 text-sm font-bold text-foreground mb-2']) }}>
    @if($icon)
        <i class="{{ $icon }} text-primary"></i>
    @endif
    <span>{{ $value ?? $slot }}</span>
</label>
