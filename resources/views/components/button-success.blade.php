@props([
    'href' => null,
    'type' => 'button',
    'size' => 'normal',
    'variant' => 'solid',
])

@php
    $sizes = [
        'small' => 'text-sm px-2.5 py-1.5',
        'normal' => 'text-base px-4 py-2',
        'large' => 'text-lg px-6 py-3',
    ];

    $variants = [
        'solid' => [
            'base' => 'bg-success text-white border-transparent shadow-sm',
            'hover' => $href ? 'hover:bg-success/90 hover:shadow-md' : 'enabled:hover:bg-success/90 enabled:hover:shadow-md',
        ],
        'outline' => [
            'base' => 'bg-transparent text-success border-success border-[1.5px]',
            'hover' => $href ? 'hover:bg-success/5' : 'enabled:hover:bg-success/5',
        ],
    ];

    $baseClasses = $variants[$variant]['base'];
    $hoverClasses = $variants[$variant]['hover'];

    $classes = $baseClasses . ' ' . $hoverClasses . ' inline-flex items-center justify-center gap-2 rounded-[0.625rem] ' . $sizes[$size] . ' text-center font-semibold tracking-wide transition-all duration-250 hover:-translate-y-[1px] active:translate-y-0 active:shadow-none focus:outline-none focus:ring-[3px] focus:ring-success/20 disabled:opacity-50 disabled:cursor-not-allowed disabled:translate-y-0';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
