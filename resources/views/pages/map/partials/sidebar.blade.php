<!-- Desktop Sidebar (Hidden on Mobile) -->
<aside class="app-rail z-30 hidden h-full w-20 flex-none flex-col items-center border-r border-border bg-neutral py-3 shadow-xl md:flex" aria-label="{{ __('Main navigation') }}">
    <!-- Logo -->
    <a href="{{ route('home') }}" class="mb-1 flex h-14 w-14 items-center justify-center rounded-xl p-1.5 transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
        data-tooltip="{{ config('app.name') }}" aria-label="{{ config('app.name') }}">
        <x-application-logo class="h-full w-full object-contain" />
    </a>

    <div class="mb-2 h-px w-10 bg-border" aria-hidden="true"></div>

    <!-- Main Navigation -->
    <nav class="flex w-full flex-1 flex-col items-center gap-4 py-2">
        <x-sidebar-nav-button id="layers" icon="ri-stack-line" activeIcon="ri-stack-fill" label="Layers" shortcut="L" />

        @if (auth()->user()->canUploadData())
            <x-sidebar-nav-button id="data" icon="ri-database-2-line" activeIcon="ri-database-2-fill" label="Data" shortcut="D" badgeId="uploadCount" />
        @endif
    </nav>

    <!-- Bottom Section -->
    <div class="flex w-full flex-col items-center gap-3 border-t border-border pt-3">
        <x-theme-toggle class="h-10 w-10 rounded-xl" data-tooltip="{{ __('Toggle dark mode') }}" />

        <x-user-dropdown-menu buttonId="userMenuBtn" dropdownId="userDropdown" placement="left" avatarSize="10" />

        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
            @csrf
        </form>
    </div>
</aside>
