<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', config('app.name'))</title>
        <meta name="description" content="@yield('meta_description', '')">
        <meta name="author" content="{{ config('app.name') }}" />

        <link type="image/svg+xml" href="{{ asset('assets/img/webgis-logo.svg') }}" rel="icon">

        <link href="{{ asset('assets/remixicon/remixicon.css') }}" rel="stylesheet">

        <style>
            /* Edge (lama) & IE: hilangkan tombol eye & clear */
            input::-ms-reveal,
            input::-ms-clear {
                display: none !important;
            }

            /* WebKit “auto-fill/contact” button (Safari/Chrome varian) */
            ::-webkit-contacts-auto-fill-button {
                visibility: hidden !important;
                display: none !important;
                pointer-events: none !important;
            }

            /* Opsional: netralisir background kuning autofill (kalau terlanjur terisi) */
            input:-webkit-autofill {
                -webkit-box-shadow: 0 0 0 1000px transparent inset !important;
                -webkit-text-fill-color: inherit !important;
                caret-color: inherit !important;
            }
        </style>
        @stack('css')
        {{ $css ?? '' }}

        <!-- Scripts -->
        <script>
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

    <body class="font-sans antialiased">
        {{ $slot }}

        <script>
            function togglePassword(id) {
                const input = document.getElementById(id);
                const icon = document.getElementById('icon-' + id);

                if (input.type === "password") {
                    input.type = "text";
                    icon.classList.remove("ri-eye-off-line");
                    icon.classList.add("ri-eye-line");
                } else {
                    input.type = "password";
                    icon.classList.remove("ri-eye-line");
                    icon.classList.add("ri-eye-off-line");
                }
            }
        </script>

        <!-- Supporting Components -->
        <x-toast />
        <x-alert-modal />
        <x-dependencies._messageAlert />


        @stack('javascript')
        {{ $javascript ?? '' }}
    </body>

</html>
