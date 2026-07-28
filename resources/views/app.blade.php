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
        ]) !!};
        window.__TENANT_BRANDING__ = {!! json_encode($loginTenant?->toLoginBrandingArray()) !!};
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
                    var skipFonts = { 'SFMono-Regular': 1, 'Segoe UI': 1 };
                    var families = presetFonts[palette] || [];
                    var googleFamilies = families.filter(function (f) { return !skipFonts[f]; });
                    if (googleFamilies.length) {
                        var params = googleFamilies.map(function (f) {
                            return 'family=' + encodeURIComponent(f) + ':wght@300;400;500;600;700';
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
