<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'dark') == 'dark']) @if(! empty($theme)) data-theme="{{ $theme }}"@endif>
    <head>
        {{-- Google Analytics only loads once the user has explicitly accepted the
             cookie banner (cookie_consent=accepted, set via useCookieConsent
             composable). `null`/`declined` → script stays out of the document so
             GA never fires a request. Matches GDPR/ePrivacy "opt-in for
             non-essential cookies" requirements. --}}
        @if (($consent ?? null) === 'accepted')
            <!-- Google tag (gtag.js) -->
            <script async src="https://www.googletagmanager.com/gtag/js?id=G-257R6ZLK1S"></script>
            <script>
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());

                gtag('config', 'G-257R6ZLK1S');
            </script>
        @endif
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark-mode preference and apply it immediately.
             The `dark` fallback matches the server-side default in HandleAppearance — the
             blade template already adds the .dark class for 'dark', so the `system` branch
             is the only remaining case this script handles. --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "dark" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#171717">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/build/manifest.webmanifest">
        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @routes
        @vite(['resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
