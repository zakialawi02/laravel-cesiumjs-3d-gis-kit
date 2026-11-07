@props([
    'buttonId' => 'userMenuBtn',
    'dropdownId' => 'userDropdown',
])

@php
    $user = auth()->user();
    $initials = collect(preg_split('/\s+/', trim($user->name)))
        ->filter()
        ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->take(2)
        ->implode('');
    $isAdmin = $user->role === \App\Enums\UserRole::Admin;
@endphp

{{-- Account menu in the map command bar. Opened and closed by pages/map/index.blade.php. --}}
<div class="relative flex-none">
    <button type="button" id="{{ $buttonId }}" class="map-avatar" aria-controls="{{ $dropdownId }}" aria-expanded="false" aria-label="{{ __('Account menu') }}">
        @if ($user->profile_photo_path)
            <img src="{{ $user->profile_photo_url }}" alt="">
        @else
            <span aria-hidden="true">{{ $initials }}</span>
        @endif
    </button>

    <div id="{{ $dropdownId }}" class="map-menu map-sheet pointer-events-none scale-95 opacity-0">
        <div class="flex items-center gap-3 border-b border-map-line p-3">
            <span class="map-avatar shrink-0" aria-hidden="true">
                @if ($user->profile_photo_path)
                    <img src="{{ $user->profile_photo_url }}" alt="">
                @else
                    {{ $initials }}
                @endif
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <p class="truncate text-sm font-semibold text-map-ink">{{ $user->name }}</p>
                    <span class="{{ $isAdmin ? 'bg-map-accent-soft text-map-accent-ink' : 'bg-map-success/12 text-map-success' }} shrink-0 rounded-md px-1.5 py-0.5 text-[0.625rem] font-bold">
                        {{ $user->role->label() }}
                    </span>
                </div>
                <p class="truncate text-xs text-map-subtle">{{ $user->email }}</p>
            </div>
        </div>

        <nav class="p-1.5" aria-label="{{ __('Account') }}">
            <a href="{{ route('dashboard') }}" class="map-menu-item">
                <i class="ri-dashboard-line" aria-hidden="true"></i>
                <span>{{ __('Dashboard') }}</span>
            </a>
            <a href="{{ route('profile.edit') }}" class="map-menu-item">
                <i class="ri-user-settings-line" aria-hidden="true"></i>
                <span>{{ __('Edit profile') }}</span>
            </a>
            <button type="button" class="map-menu-item" data-theme-toggle aria-pressed="false">
                <i class="ri-moon-clear-line dark:hidden" aria-hidden="true"></i>
                <i class="ri-sun-line hidden dark:inline-block" aria-hidden="true"></i>
                <span class="flex-1">{{ __('Dark mode') }}</span>
                <span class="map-switch-mini" aria-hidden="true"></span>
            </button>
        </nav>

        <div class="border-t border-map-line p-1.5">
            <button type="button" class="map-menu-item map-menu-item--danger" onclick="document.getElementById('logout-form').submit();">
                <i class="ri-logout-box-r-line" aria-hidden="true"></i>
                <span>{{ __('Sign out') }}</span>
            </button>
        </div>
    </div>
</div>
