{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach($pages as $p)
@foreach(['ar', 'en'] as $lang)
    <url>
        <loc>{{ $p[$lang] }}</loc>
        <lastmod>{{ $lastmod->toDateString() }}</lastmod>
        <changefreq>{{ $p['freq'] }}</changefreq>
        <priority>{{ $p['priority'] }}</priority>
        <xhtml:link rel="alternate" hreflang="ar-OM" href="{{ $p['ar'] }}"/>
        <xhtml:link rel="alternate" hreflang="en" href="{{ $p['en'] }}"/>
        <xhtml:link rel="alternate" hreflang="x-default" href="{{ $p['ar'] }}"/>
    </url>
@endforeach
@endforeach
</urlset>
