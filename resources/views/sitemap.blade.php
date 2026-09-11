{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}

<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

    {{-- Homepage --}}
    <url>
        <loc>{{ url('/') }}</loc>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>

    {{-- Main Pages --}}
    <url>
        <loc>{{ url('/packages') }}</loc>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>

    <url>
        <loc>{{ url('/destinations') }}</loc>
        <changefreq>weekly</changefreq>
        <priority>0.9</priority>
    </url>

    <url>
        <loc>{{ url('/about') }}</loc>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>

    <url>
        <loc>{{ url('/contact') }}</loc>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>

    <url>
        <loc>{{ url('/faq') }}</loc>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>

    <url>
        <loc>{{ url('/gallery') }}</loc>
        <changefreq>weekly</changefreq>
        <priority>0.6</priority>
    </url>

    <url>
        <loc>{{ url('/team') }}</loc>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>

    <url>
        <loc>{{ url('/testimonials') }}</loc>
        <changefreq>weekly</changefreq>
        <priority>0.6</priority>
    </url>


    {{-- Tour Packages --}}
    @foreach ($packages as $package)
    <url>
        <loc>{{ url('/packages/' . $package->slug) }}</loc>

        @if ($package->updated_at)
        <lastmod>{{ $package->updated_at->toAtomString() }}</lastmod>
        @endif

        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    @endforeach


    {{-- Destinations --}}
    @foreach ($destinations as $destination)
    <url>
        <loc>{{ url('/destinations/' . $destination->slug) }}</loc>

        @if ($destination->updated_at)
        <lastmod>{{ $destination->updated_at->toAtomString() }}</lastmod>
        @endif

        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    @endforeach


    {{-- Places --}}
    @foreach ($places as $place)
    <url>
        <loc>{{ url('/places/' . $place->slug) }}</loc>

        @if ($place->updated_at)
        <lastmod>{{ $place->updated_at->toAtomString() }}</lastmod>
        @endif

        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
    @endforeach

</urlset>
