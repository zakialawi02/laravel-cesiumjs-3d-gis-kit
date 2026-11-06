<!-- Mobile Bottom Navigation (Hidden on md and up) -->
<nav aria-label="{{ __('Main navigation') }}" class="z-40 flex h-16 w-full flex-none items-center justify-around border-t border-border bg-neutral px-2 pb-safe shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.1)] md:hidden">
    <x-mobile-nav-button id="layers" icon="ri-stack-line" activeIcon="ri-stack-fill" label="Layers" />

    @if (auth()->user()->canUploadData())
        <x-mobile-nav-button id="data" icon="ri-database-2-line" activeIcon="ri-database-2-fill" label="Data" badgeId="uploadCountMobile" />
    @endif
</nav>
