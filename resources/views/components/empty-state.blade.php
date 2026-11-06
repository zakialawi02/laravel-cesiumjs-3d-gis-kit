@props([
    'message',
    'subtitle' => '',
    'type' => 'empty', // 'empty' or 'error'
    'icon' => null, // Override default icon
    'iconClass' => 'text-lg', // Icon size/styling
    'id' => '', // optional id for targeting
    'containerClass' => 'py-4', // Container styling
])

<div {{ $id ? 'id='.$id : '' }} class="{{ $containerClass }} text-center flex flex-col items-center justify-center gap-1">
    @if($type === 'error')
        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-error/10 text-error mb-1">
            <i class="{{ $icon ?? 'ri-error-warning-line' }} {{ $iconClass }}"></i>
        </div>
    @else
        @if($icon)
            <i class="{{ $icon }} {{ $iconClass }} text-foreground/20 mb-2 block"></i>
        @else
            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-foreground/5 text-foreground/40 mb-1">
                <i class="ri-inbox-line {{ $iconClass }}"></i>
            </div>
        @endif
    @endif
    
    <p class="text-[11px] {{ $type === 'error' ? 'text-error font-medium' : 'text-foreground/50' }}">{!! $message !!}</p>
    
    @if($subtitle)
        <p class="text-[10px] text-foreground/30 mt-0.5">{!! $subtitle !!}</p>
    @endif
</div>
