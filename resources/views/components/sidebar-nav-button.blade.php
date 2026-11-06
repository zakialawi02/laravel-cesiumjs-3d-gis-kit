@props([
    'id',
    'icon',
    'activeIcon' => null,
    'label' => null,
    'shortcut' => null,
    'badgeId' => null,
])

{{-- Desktop rail button. index.blade.php toggles `.active` and aria-expanded when its panel opens. --}}
<button type="button" id="{{ $id }}ToggleBtn" class="nav-btn group" aria-controls="{{ $id }}Panel" aria-expanded="false"
    @if ($label) aria-label="{{ $label }}" @endif
    @if ($shortcut) aria-keyshortcuts="{{ $shortcut }}" data-tooltip="{{ $label }} ({{ $shortcut }})" @elseif ($label) data-tooltip="{{ $label }}" @endif>
    <span class="nav-indicator" aria-hidden="true"></span>
    <span id="{{ $id }}IconContainer" class="nav-icon relative h-10 w-10 rounded-xl">
        <i class="nav-icon-default {{ $icon }} text-xl" aria-hidden="true"></i>
        <i class="nav-icon-active {{ $activeIcon ?? $icon }} text-xl" aria-hidden="true"></i>
        @if ($badgeId)
            <span id="{{ $badgeId }}" class="nav-badge" hidden>0</span>
        @endif
    </span>
    @if ($label)
        <span id="{{ $id }}Label" class="nav-label text-[10px]">{{ $label }}</span>
    @endif
</button>
