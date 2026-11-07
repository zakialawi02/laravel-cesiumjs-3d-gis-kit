{{-- Phone bottom navigation (hidden from md up, where the switches live in the command bar). --}}
<nav aria-label="{{ __('Main navigation') }}" class="map-tabbar map-glass">
    <x-mobile-nav-button id="layers" icon="ri-stack-line" activeIcon="ri-stack-fill" label="Layers" />

    @if (auth()->user()->canUploadData())
        <x-mobile-nav-button id="data" icon="ri-database-2-line" activeIcon="ri-database-2-fill" label="Data" badgeId="uploadCountMobile" />
    @endif
</nav>
