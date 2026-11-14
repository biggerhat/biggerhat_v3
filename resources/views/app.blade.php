<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'dark') == 'dark']) @if(! empty($theme)) data-theme="{{ $theme }}"@endif @if(! empty($textSize)) data-text-size="{{ $textSize }}"@endif>
    <head>
        {{-- Google Analytics with Consent Mode v2. gtag always loads, but
             analytics_storage defaults to 'denied' until the user accepts the
             cookie banner (cookie_consent=accepted, set via useCookieConsent).
             While denied, GA sends cookieless pings (no identifiers) used for
             aggregate behavioural modelling — so traffic isn't lost while still
             honouring GDPR/ePrivacy opt-in for cookies. The server already knows
             the choice from the cookie, so the correct default is seeded on the
             first byte; mid-session accept/decline calls gtag('consent','update')
             from useCookieConsent (no reload). Ad signals stay denied — we run no
             ads. --}}
        <!-- Google tag (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=G-257R6ZLK1S"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}

            gtag('consent', 'default', {
                'analytics_storage': '{{ ($consent ?? null) === 'accepted' ? 'granted' : 'denied' }}',
                'ad_storage': 'denied',
                'ad_user_data': 'denied',
                'ad_personalization': 'denied',
            });

            gtag('js', new Date());
            gtag('config', 'G-257R6ZLK1S');
        </script>
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

        {{-- Start-up watchdog. If the app bundle hasn't mounted after 10s (a stale or half-cached service worker
             serving a missing chunk, a flaky connection) drop the service worker and its caches and reload once;
             if that doesn't help, show a Reload button instead of leaving a blank screen. --}}
        <script>
            (function () {
                var KEY = 'bh-watchdog-reloaded';
                setTimeout(function () {
                    if (window.__appMounted) {
                        try { sessionStorage.removeItem(KEY); } catch (e) {}
                        return;
                    }
                    var cleanup = [];
                    if (navigator.serviceWorker && navigator.serviceWorker.getRegistrations) {
                        cleanup.push(navigator.serviceWorker.getRegistrations().then(function (rs) {
                            return Promise.all(rs.map(function (r) { return r.unregister(); }));
                        }));
                    }
                    if (window.caches && caches.keys) {
                        cleanup.push(caches.keys().then(function (ks) {
                            return Promise.all(ks.map(function (k) { return caches.delete(k); }));
                        }));
                    }
                    Promise.all(cleanup).catch(function () {}).then(function () {
                        var again = false;
                        try { again = sessionStorage.getItem(KEY) === '1'; sessionStorage.setItem(KEY, '1'); } catch (e) {}
                        if (!again) { location.reload(); return; }
                        var bar = document.createElement('div');
                        bar.setAttribute('role', 'alert');
                        bar.style.cssText = 'position:fixed;left:0;right:0;bottom:0;z-index:99999;padding:16px;background:#171717;color:#fff;font:16px/1.4 system-ui,sans-serif;text-align:center';
                        bar.appendChild(document.createTextNode('BiggerHat is taking too long to load. '));
                        var btn = document.createElement('button');
                        btn.textContent = 'Reload';
                        btn.style.cssText = 'margin-left:8px;padding:10px 18px;border:0;border-radius:6px;background:#f5c84b;color:#000;font:inherit;font-weight:600';
                        btn.onclick = function () { location.reload(); };
                        bar.appendChild(btn);
                        document.body.appendChild(bar);
                    });
                }, 10000);
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
        {{-- Head tags rendered by the Inertia SSR service (page <Head>/<SeoHead> plus the layout's DefaultSeo). They come
             first so crawlers see the page's own <title>; the controller-supplied/default tags below are only the
             fallback for when SSR is off or failed, otherwise the document would carry two <title>s and the first
             (generic) one would win. --}}
        @inertiaHead

        @if (empty($__inertiaSsrResponse))
        {{-- Per-page meta is supplied by controllers via
             `->withViewData(['page_meta' => $this->pageMeta(...)])` (see
             App\Http\Controllers\Concerns\BuildsPageMeta). Anything not
             overridden falls through to the site-wide defaults below so
             link unfurlers always see *something* sensible. The Vue
             <SeoHead> component replaces these on client-side navigation
             via Inertia's head-deduping — crawlers (no JS) see the
             server-rendered values from this Blade template. --}}
        @php
            $defaultDescription = 'BiggerHat is the comprehensive Malifaux database — characters, upgrades, keywords, lore, build tools, tournament tracker, and more. The fastest way to find anything Malifaux.';
            $defaultShortDescription = 'The comprehensive Malifaux database — characters, upgrades, keywords, lore, and tools.';
            $defaultImage = url('/images/biggerhat-og.png');
            $metaTitle = $page_meta['title'] ?? 'BiggerHat';
            $metaDescription = $page_meta['description'] ?? null;
            $metaImage = $page_meta['image'] ?? $defaultImage;
            $metaType = $page_meta['type'] ?? 'website';
        @endphp
        <title inertia>{{ $metaTitle }}</title>

        <meta name="description" content="{{ $metaDescription ?? $defaultDescription }}" inertia="description">
        <link rel="canonical" href="{{ url()->current() }}" inertia="canonical">
        <meta property="og:type" content="{{ $metaType }}" inertia="og:type">
        <meta property="og:site_name" content="BiggerHat" inertia="og:site_name">
        <meta property="og:title" content="{{ $metaTitle }}" inertia="og:title">
        <meta property="og:description" content="{{ $metaDescription ?? $defaultShortDescription }}" inertia="og:description">
        <meta property="og:url" content="{{ url()->current() }}" inertia="og:url">
        <meta property="og:image" content="{{ $metaImage }}" inertia="og:image">
        <meta name="twitter:card" content="summary_large_image" inertia="twitter:card">
        <meta name="twitter:title" content="{{ $metaTitle }}" inertia="twitter:title">
        <meta name="twitter:description" content="{{ $metaDescription ?? $defaultShortDescription }}" inertia="twitter:description">
        <meta name="twitter:image" content="{{ $metaImage }}" inertia="twitter:image">
        @endif

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @routes
        @vite(['resources/js/app.ts'])
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
