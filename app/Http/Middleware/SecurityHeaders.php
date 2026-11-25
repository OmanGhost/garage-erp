<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $h = $response->headers;
        $h->set('X-Frame-Options', 'SAMEORIGIN');
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');
        $h->set('X-XSS-Protection', '0');
        // only the public marketing site and tenant storefronts may be indexed by search engines
        $public = $request->routeIs('site.*', 'store.*') && \App\Support\SiteSettings::get('indexing_enabled') === '1';
        if (! $public && ! $request->routeIs('site.robots', 'site.sitemap')) {
            $h->set('X-Robots-Tag', 'noindex, nofollow');
        }
        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
