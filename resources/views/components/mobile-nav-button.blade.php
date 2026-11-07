@props([
    'id',
    'icon',
    'activeIcon' => null,
    'label' => null,
    'badgeId' => null,
])

{{-- Phone bottom-bar switch for a side panel. pages/map/index.blade.php toggles `.active` and aria-expanded. --}}
<button type="button" id="{{ $id }}ToggleBtnMobile" class="map-tab" aria-controls="{{ $id }}Panel" aria-expanded="false"
    @if ($label) aria-label="{{ $label }}" @endif>
    <span id="{{ $id }}IconContainerMobile" class="map-tab-icon">
        <i class="nav-icon-default {{ $icon }}" aria-hidden="true"></i>
        <i class="nav-icon-active {{ $activeIcon ?? $icon }}" aria-hidden="true"></i>
        @if ($badgeId)
            <span id="{{ $badgeId }}" class="map-badge" hidden>0</span>
        @endif
    </span>
    @if ($label)
        <span id="{{ $id }}LabelMobile" class="map-tab-label">{{ $label }}</span>
    @endif
</button>
