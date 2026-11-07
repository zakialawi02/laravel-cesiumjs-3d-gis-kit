@props([
    'iconClass' => 'text-xl',
])

<button {{ $attributes->merge(['type' => 'button', 'class' => 'map-icon-btn -mr-1 -mt-0.5', 'aria-label' => __('Close')]) }}>
    <i class="ri-close-line {{ $iconClass }}" aria-hidden="true"></i>
</button>
