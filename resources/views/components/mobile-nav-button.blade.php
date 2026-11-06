@props([
    'id',
    'icon',
    'activeIcon' => null,
    'label' => null,
    'badgeId' => null,
])

{{-- Mobile bottom-bar button. Shares the `.nav-btn` state styles with the desktop rail. --}}
<button type="button" id="{{ $id }}ToggleBtnMobile" class="nav-btn nav-btn-mobile group gap-1" aria-controls="{{ $id }}Panel" aria-expanded="false"
    @if ($label) aria-label="{{ $label }}" @endif>
    <span id="{{ $id }}IconContainerMobile" class="nav-icon relative h-8 w-8 rounded-lg">
        <i class="nav-icon-default {{ $icon }} text-lg" aria-hidden="true"></i>
        <i class="nav-icon-active {{ $activeIcon ?? $icon }} text-lg" aria-hidden="true"></i>
        @if ($badgeId)
            <span id="{{ $badgeId }}" class="nav-badge" hidden>0</span>
        @endif
    </span>
    @if ($label)
        <span id="{{ $id }}LabelMobile" class="nav-label text-[9px]">{{ $label }}</span>
    @endif
</button>
