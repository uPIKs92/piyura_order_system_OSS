<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('branding.app_name') }}</title>
    @php
        $faviconPath = public_path('favicon.ico');
        $faviconV = is_file($faviconPath) ? substr((string) md5_file($faviconPath), 0, 10) : '0';
    @endphp
    <link rel="icon" href="{{ asset('favicon.ico') }}?v={{ $faviconV }}" type="image/x-icon" sizes="any">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v={{ $faviconV }}" type="image/x-icon">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    <meta name="theme-color" content="#212121">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ config('branding.app_name') }}">
    @include('partials._apple-splash')
    @php
        $loginTenant = \App\Support\LoginTenant::resolve();
    @endphp
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <script nonce="{{ $cspNonce }}">
        window.__APP_BRANDING__ = {!! json_encode([
            'app_name' => config('branding.app_name'),
            'platform_name' => config('branding.platform_name'),
            'show_platform_credit_on_invoice' => config('branding.show_platform_credit_on_invoice'),
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
        window.__TENANT_BRANDING__ = {!! json_encode($loginTenant?->toLoginBrandingArray(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
    </script>
    <script nonce="{{ $cspNonce }}">
        (function () {
            try {
                var theme = localStorage.getItem('theme');
                var isDark = theme === 'dark' || (
                    (theme === 'system' || theme === null) &&
                    window.matchMedia('(prefers-color-scheme: dark)').matches
                );
                if (isDark) {
                    document.documentElement.classList.add('dark');
                }
                var palette = localStorage.getItem('order-tracker-palette') || localStorage.getItem('order-tracker-accent');
                if (palette && palette !== 'neutral') {
                    document.documentElement.setAttribute('data-palette', palette);
                    var presetFonts = @json(json_decode(file_get_contents(resource_path('js/lib/theme-preset-fonts.json')), true));
                    var queries = presetFonts[palette] || [];
                    if (queries.length) {
                        var params = queries.map(function (q) {
                            return 'family=' + q;
                        }).join('&');
                        var link = document.createElement('link');
                        link.id = 'palette-google-fonts';
                        link.rel = 'stylesheet';
                        link.href = 'https://fonts.googleapis.com/css2?' + params + '&display=swap';
                        document.head.appendChild(link);
                    }
                }
            } catch (e) {}
        })();
    </script>
    <script nonce="{{ $cspNonce }}">
        (function () {
            if (!('serviceWorker' in navigator)) return;
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('{{ asset('sw.js') }}').catch(function () {});
            });
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/main.tsx'])
    <style>
        * { -webkit-tap-highlight-color: transparent; }
        .safe-top { padding-top: env(safe-area-inset-top, 0); }
    </style>
</head>
<body class="h-full bg-background antialiased">
    <div id="root" class="h-full"></div>
</body>
</html>
