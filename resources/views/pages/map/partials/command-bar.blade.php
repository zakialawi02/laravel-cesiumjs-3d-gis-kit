{{-- Floating command bar: brand, side-panel switches and account menu (every breakpoint). --}}
<header class="map-bar map-glass">
    <a href="{{ route('home') }}" class="map-brand" aria-label="{{ config('app.name') }}">
        <x-application-logo class="size-8 shrink-0" />
        <span class="map-brand-text">
            <span class="map-brand-name">{{ config('app.name') }}</span>
            <span class="map-brand-sub">{{ __('3D map viewer') }}</span>
        </span>
    </a>

    <nav class="map-nav" aria-label="{{ __('Main navigation') }}">
        <x-map-nav-button id="layers" icon="ri-stack-line" activeIcon="ri-stack-fill" label="Layers" shortcut="L" />

        @if (auth()->user()->canUploadData())
            <x-map-nav-button id="data" icon="ri-database-2-line" activeIcon="ri-database-2-fill" label="Data" shortcut="D" badgeId="uploadCount" />
        @endif
    </nav>

    <x-user-dropdown-menu buttonId="userMenuBtn" dropdownId="userDropdown" />

    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
        @csrf
    </form>
</header>
