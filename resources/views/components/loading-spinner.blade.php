@props([
    'message' => 'Loading...',
    'id' => null,
])

<div {{ $attributes->merge(['class' => 'py-6 text-center']) }} @if($id) id="{{ $id }}" @endif>
    <i class="ri-refresh-line text-2xl text-primary animate-spin mb-1 block"></i>
    <p class="text-[10px] text-foreground/50 italic">{{ $message }}</p>
</div>
