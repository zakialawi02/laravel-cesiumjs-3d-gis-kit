@props(['disabled' => false, 'icon' => null, 'size' => 'normal', 'isError' => false])

@php
    $sizes = [
        'small' => 'text-sm px-2 py-1.5',
        'normal' => 'text-[0.85rem] px-3 py-2',
        'large' => 'text-lg px-4 py-3',
    ];

    $sizeClasses = $sizes[$size] ?? $sizes['normal'];
@endphp

<div class="relative flex items-center">
    @if ($icon)
        <span class="pointer-events-none absolute left-3.5 z-10 flex items-center text-[1rem] text-base-content-muted">
            <i class="{{ $icon }}"></i>
        </span>
    @endif

    <input @disabled($disabled) {{ $attributes->merge(['class' => 'w-full rounded-[0.625rem] border-[1.5px] ' . ($isError ? 'border-red-500' : 'border-border') . ' bg-input ' . $sizeClasses . ' ' . ($icon ? 'pl-10' : '') . ' text-foreground outline-none transition-all duration-200 placeholder:font-normal placeholder:text-[#a0aec0] focus:border-primary focus:ring-[3px] focus:ring-primary/10']) }}>

    {{ $slot }}
</div>