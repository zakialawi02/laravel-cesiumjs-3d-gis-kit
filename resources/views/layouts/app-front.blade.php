<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-dvh overflow-hidden overscroll-none">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta http-equiv="X-UA-Compatible" content="ie=edge">

        <meta name="description" content="@yield('meta_description', '')">
        <meta name="author" content="@yield('meta_author', config('app.name'))">
        <meta name="keywords" content="@yield('meta_keywords', '')">

        <meta property="og:title" content="@yield('og_title', config('app.name'))" />
        <meta property="og:type" content="@yield('og_type', 'website')" />
        <meta property="og:url" content="@yield('og_url', url()->current())" />
        <meta property="og:description" content="@yield('og_description', config('app.name'))" />
        <meta property="og:image" content="@yield('og_image', asset('assets/img/webgis-icon.png'))" />

        <meta name="robots" content="@yield('meta_robots', 'index,follow')">

        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link type="image/svg+xml" href="{{ asset('assets/img/webgis-logo.svg') }}" rel="shortcut icon">

        <title>@yield('title') • {{ config('app.name') }}</title>

        <link href="{{ asset('assets/remixicon/remixicon.css') }}" rel="stylesheet">

        @stack('css')
        {{ $css ?? '' }}

        <!-- Scripts -->
        <script>
            window.APP_GIS_CONFIG = @js(config('geodata.utm'));
            window.CESIUM_BASE_URL = @js(asset('cesium').'/');
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

    </head>

    <body class="bg-background text-foreground h-dvh w-full overflow-hidden overscroll-none font-sans antialiased">
        <main class="h-full w-full overflow-hidden overscroll-none">
            {{ $slot }}
        </main>


        <!-- Supporting Components -->
        <x-toast />
        <x-alert-modal />
        <x-dependencies._messageAlert />


        @stack('javascript')
        {{ $javascript ?? '' }}
    </body>

</html>
