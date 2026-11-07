@props([
    'id',
    'icon',
    'activeIcon' => null,
    'label',
    'shortcut' => null,
    'badgeId' => null,
])

{{-- Command bar switch for a side panel. pages/map/index.blade.php toggles `.active` and aria-expanded. --}}
<button type="button" id="{{ $id }}ToggleBtn" class="map-nav-btn" aria-controls="{{ $id }}Panel" aria-expanded="false" aria-label="{{ $label }}"
    data-tip="{{ $shortcut ? $label.' ('.$shortcut.')' : $label }}" data-tip-side="bottom" @if ($shortcut) aria-keyshortcuts="{{ $shortcut }}" @endif>
    <i class="nav-icon-default {{ $icon }}" aria-hidden="true"></i>
    <i class="nav-icon-active {{ $activeIcon ?? $icon }}" aria-hidden="true"></i>
    <span>{{ $label }}</span>
    @if ($badgeId)
        <span id="{{ $badgeId }}" class="map-badge" hidden>0</span>
    @endif
</button>
