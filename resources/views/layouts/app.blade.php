<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex,nofollow">

        <title>{{ $title }} • {{ config('app.name') }}</title>

        <link type="image/svg+xml" href="{{ asset('assets/img/webgis-logo.svg') }}" rel="icon">
        <link href="{{ asset('assets/remixicon/remixicon.css') }}" rel="stylesheet">

        <script>
            window.APP_GIS_CONFIG = @js(config('geodata.utm'));
            (function() {
                try {
                    if (localStorage.getItem("hs_theme") === "dark") {
                        document.documentElement.classList.add("dark");
                    }
                } catch (error) {
                    // Storage unavailable: keep the light theme.
                }
            })();
        </script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('css')
    </head>

    <body class="bg-background text-foreground font-sans antialiased">
        @php
            $navSections = [
                ['title' => null, 'items' => [
                    ['route' => 'home', 'active' => 'home', 'icon' => 'ri-global-line', 'label' => __('3D Map')],
                    ['route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'ri-dashboard-line', 'label' => __('Dashboard')],
                ]],
            ];

            if (auth()->user()->canManageData()) {
                $navSections[] = ['title' => __('Data Management'), 'items' => [
                    ['route' => 'dashboard.layers.index', 'active' => 'dashboard.layers.*', 'icon' => 'ri-database-2-line', 'label' => __('Layer Management')],
                    ['route' => 'dashboard.layer-categories.index', 'active' => 'dashboard.layer-categories.*', 'icon' => 'ri-folder-chart-line', 'label' => __('Layer Categories')],
                ]];
            }

            if (auth()->user()->canManageUsers()) {
                $navSections[] = ['title' => __('Administration'), 'items' => [
                    ['route' => 'dashboard.users.index', 'active' => 'dashboard.users.*', 'icon' => 'ri-team-line', 'label' => __('Users')],
                ]];
            }

            $navSections[] = ['title' => __('Account'), 'items' => [
                ['route' => 'profile.edit', 'active' => 'profile.*', 'icon' => 'ri-user-settings-line', 'label' => __('Edit Profile')],
            ]];
        @endphp

        <!-- Sidebar (off-canvas below lg) -->
        <div class="fixed inset-0 z-40 hidden bg-black/40 lg:hidden" data-sidebar-backdrop></div>
        <aside id="dashboardSidebar" data-sidebar
            class="bg-neutral border-border fixed inset-y-0 start-0 z-50 flex w-64 -translate-x-full flex-col border-e transition-transform duration-300 lg:translate-x-0"
            aria-label="{{ __('Dashboard navigation') }}">
            <div class="flex items-center justify-between px-5 py-3">
                <a href="{{ route('home') }}" class="flex items-center gap-2 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                    <x-application-logo class="h-9 w-9" />
                    <span class="text-sm font-extrabold uppercase tracking-wider text-primary">{{ config('app.name') }}</span>
                </a>
                <button type="button" class="text-foreground/60 hover:bg-muted rounded-lg p-1.5 lg:hidden" data-sidebar-close aria-label="{{ __('Close menu') }}">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto px-3 py-2">
                @foreach ($navSections as $section)
                    @if ($section['title'])
                        <p class="text-foreground/45 mb-1 mt-4 px-3 text-[10px] font-bold uppercase tracking-wider">{{ $section['title'] }}</p>
                    @endif
                    <ul class="space-y-1">
                        @foreach ($section['items'] as $item)
                            @php($isActive = request()->routeIs($item['active']))
                            <li>
                                <a href="{{ route($item['route']) }}" @if ($isActive) aria-current="page" @endif
                                    @class([
                                        'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary',
                                        'bg-primary/10 text-primary' => $isActive,
                                        'text-foreground/75 hover:bg-muted hover:text-primary' => !$isActive,
                                    ])>
                                    <i class="{{ $item['icon'] }} text-lg"></i>
                                    <span>{{ $item['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </nav>

            <div class="border-border border-t p-3">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-error hover:bg-error/10 flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold transition-colors">
                        <i class="ri-logout-box-line text-lg"></i>
                        <span>{{ __('Sign Out') }}</span>
                    </button>
                </form>
            </div>
        </aside>

        <!-- Header -->
        <header class="bg-neutral/90 border-border sticky top-0 z-30 border-b backdrop-blur lg:ps-64">
            <div class="flex items-center gap-3 px-4 py-2.5 sm:px-6">
                <button type="button" class="text-foreground/70 hover:bg-muted rounded-lg border border-border p-1.5 lg:hidden" data-sidebar-open aria-controls="dashboardSidebar"
                    aria-expanded="false" aria-label="{{ __('Open menu') }}">
                    <i class="ri-menu-2-line text-lg"></i>
                </button>
                <h1 class="text-foreground truncate text-base font-bold">{{ $title }}</h1>

                <div class="ms-auto flex items-center gap-2">
                    <x-theme-toggle />
                    <div class="flex items-center gap-2 rounded-full border border-border py-1 pe-3 ps-1">
                        <img src="{{ auth()->user()->profile_photo_url }}" alt="" class="h-7 w-7 rounded-full object-cover">
                        <div class="hidden leading-tight sm:block">
                            <p class="text-foreground max-w-40 truncate text-xs font-bold">{{ auth()->user()->name }}</p>
                            <p class="text-foreground/60 text-[10px] font-semibold uppercase tracking-wider">{{ auth()->user()->role->label() }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="lg:ps-64">
            <div class="w-full p-4 sm:p-6">
                {{ $slot }}
            </div>
        </main>

        <x-toast />
        <x-dependencies._messageAlert />

        @stack('javascript')
    </body>

</html>
