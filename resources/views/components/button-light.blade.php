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
            'base' => 'bg-neutral text-foreground border-border border-[1.5px] shadow-sm',
            'hover' => $href ? 'hover:bg-muted/50 hover:border-primary/30' : 'enabled:hover:bg-muted/50 enabled:hover:border-primary/30',
        ],
        'outline' => [
            'base' => 'bg-transparent text-foreground border-border border-[1.5px]',
            'hover' => $href ? 'hover:bg-neutral/50' : 'enabled:hover:bg-neutral/50',
        ],
        'transparent' => [
            'base' => 'bg-transparent text-foreground',
            'hover' => $href ? 'hover:bg-foreground/10' : 'enabled:hover:bg-foreground/10',
        ],
    ];

    $baseClasses = $variants[$variant]['base'];
    $hoverClasses = $variants[$variant]['hover'];

    $classes =
        $baseClasses .
        ' ' .
        $hoverClasses .
        ' inline-flex items-center justify-center gap-2 rounded-[0.625rem] ' .
        $sizes[$size] .
        ' text-center font-semibold tracking-wide transition-all duration-250 hover:-translate-y-[1px] active:translate-y-0 active:shadow-none focus:outline-none focus:ring-[3px] focus:ring-primary/10 disabled:opacity-50 disabled:cursor-not-allowed disabled:translate-y-0';
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
