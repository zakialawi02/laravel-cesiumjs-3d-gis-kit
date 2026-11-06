@props([
    'iconClass' => 'text-2xl',
])

<button {{ $attributes->merge(['type' => 'button', 'class' => 'text-foreground/70 hover:text-primary transition-colors']) }}>
    <i class="ri-close-line {{ $iconClass }}"></i>
</button>
