<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="app-base" content="{{ parse_url(config('app.url'), PHP_URL_PATH) ?? '' }}">
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
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+Devanagari:wght@500&family=Playfair+Display:wght@500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <title inertia>{{ $siteName }}</title>
    @routes
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
