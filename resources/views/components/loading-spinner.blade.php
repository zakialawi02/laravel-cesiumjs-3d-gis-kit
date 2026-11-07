@props([
    'message' => 'Loading...',
    'id' => null,
])

<div {{ $attributes->merge(['class' => 'flex items-center justify-center gap-2 py-6 text-xs text-map-subtle']) }} @if ($id) id="{{ $id }}" @endif role="status">
    <i class="ri-loader-4-line animate-spin text-base text-map-accent-ink" aria-hidden="true"></i>
    <span>{{ $message }}</span>
</div>
