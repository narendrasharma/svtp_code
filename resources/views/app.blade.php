@php
    // Phase 13A: document lang/dir resolve server-side to avoid flicker.
    // SetLocale middleware already set the app locale; fall back safely.
    $docLocale = str_replace('_', '-', app()->getLocale() ?: config('app.locale', 'en'));
    try {
        $docDirection = \App\Support\Localization::direction(app()->getLocale());
    } catch (\Throwable) {
        $docDirection = 'ltr';
    }
@endphp
<!DOCTYPE html>
<html lang="{{ $docLocale }}" dir="{{ $docDirection }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="app-base" content="{{ parse_url(config('app.url'), PHP_URL_PATH) ?? '' }}">
    <script>
        // PRE-12B.9 admin theme: applied before first paint to avoid flashing
        // the wrong theme. Choice persisted as svtp-admin-theme
        // (light | dark | system); falls back to the OS preference.
        try {
            (function () {
                var choice = localStorage.getItem('svtp-admin-theme') || 'system';
                var theme = choice;
                if (choice === 'system') {
                    theme = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
                }
                document.documentElement.setAttribute('data-admin-theme', theme);
                document.documentElement.setAttribute('data-admin-theme-choice', choice);
            })();
        } catch (e) {
            document.documentElement.setAttribute('data-admin-theme', 'light');
        }
    </script>
        @php
            $siteSettings =
           \App\Models\Setting::query()
               ->pluck('value', 'key');

       $siteFavicon =
           $siteSettings->get('site_favicon');

       $siteName =
           $siteSettings->get(
               'site_name',
               config('app.name')
           );
        @endphp


        @if($siteFavicon)

        <link
        rel="icon"
        href="{{ asset('storage/' . $siteFavicon) }}"
        >

        @else

        <link
        rel="icon"
        type="image/svg+xml"
        href="{{ Vite::asset('resources/images/favicon.svg') }}"
        >

        @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=DM+Sans:wght@400;500;600;700&family=Noto+Sans+Arabic:wght@400;500;600&family=Noto+Sans+Devanagari:wght@400;500;600&family=Noto+Serif+Devanagari:wght@500;600&display=swap" rel="stylesheet">
    <title inertia>{{ $siteName }}</title>
    @routes
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
