<!-- Mobile Header (Hidden on md and up) -->
<header class="z-40 flex h-12 w-full flex-none items-center justify-between border-b border-border bg-neutral px-4 shadow-sm md:hidden">
    <div class="h-6">
        <x-application-logo class="h-full w-auto object-contain" />
    </div>
    <div class="flex items-center gap-1">
        <x-theme-toggle class="h-8 w-8" />
        <x-user-dropdown-menu buttonId="userMenuBtnMobile" dropdownId="userDropdownMobile" placement="bottom" avatarSize="8" />
    </div>
</header>
