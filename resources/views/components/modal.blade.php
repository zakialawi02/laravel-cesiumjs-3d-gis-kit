@props(['name', 'show' => false, 'maxWidth' => '2xl'])

@php
    $maxWidth = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
    ][$maxWidth];
@endphp

<div {{ $attributes->except('focusable')->merge(['class' => 'fixed inset-0 z-100 flex items-center justify-center px-4 py-6 sm:px-0']) }}
    data-modal="{{ $name }}" data-modal-show="{{ $show ? 'true' : 'false' }}" @if ($attributes->has('focusable')) data-modal-focusable @endif
    role="dialog" aria-modal="true" aria-hidden="{{ $show ? 'false' : 'true' }}" tabindex="-1" style="display: {{ $show ? 'flex' : 'none' }};">
    <!-- Backdrop -->
    <div class="fixed inset-0 z-40 bg-black/50 backdrop-blur-[2px]" data-modal-close></div>

    <!-- Modal Content -->
    <div class="{{ $maxWidth }} bg-neutral border-border relative z-50 max-h-[calc(100dvh-3rem)] w-full transform overflow-y-auto rounded-2xl border shadow-2xl transition-all" data-modal-content>
        {{ $slot }}
    </div>
</div>
