<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser security headers for every web response.
 *
 * The Content-Security-Policy allows scripts only from this site or tagged
 * with this request's random nonce (no other inline script, no third-party
 * script), styles from this site and Bunny Fonts, map tiles from the
 * configured tile server, and no framing by other sites.
 * It is skipped while the Vite dev server runs (`npm run dev`), whose
 * hot-reload client lives on another port. HSTS is sent only over HTTPS in
 * production, so local HTTP keeps working.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $csp = config('meezan.security.csp') && ! Vite::isRunningHot();

        // A fresh nonce per request, set before the view renders so @vite (and
        // dev tooling that honours Vite's nonce, such as Laravel Boost) can tag
        // its scripts. Any other inline script stays blocked.
        $nonce = $csp ? Vite::useCspNonce() : null;

        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            // Geolocation only for "Use my location" in the dealer listing form.
            'Permissions-Policy' => 'camera=(), microphone=(), payment=(), usb=(), geolocation=(self)',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        if ($csp) {
            $headers['Content-Security-Policy'] = $this->policy($nonce);
        }

        if ($request->isSecure() && app()->isProduction()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            // Never override a header a controller set deliberately (e.g. document downloads).
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    protected function policy(?string $nonce): string
    {
        $tiles = $this->origin(config('meezan.map.tile_url'));
        $storage = $this->origin(config('filesystems.disks.public.url'));

        return implode('; ', [
            "default-src 'self'",
            trim("script-src 'self' ".($nonce ? "'nonce-{$nonce}'" : '')),
            // Leaflet and a few components set element styles at runtime. No nonce
            // here: a nonce would make browsers ignore 'unsafe-inline' for styles.
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "font-src 'self' https://fonts.bunny.net data:",
            trim("img-src 'self' data: blob: {$tiles} {$storage}"),
            "connect-src 'self'",
            "frame-ancestors 'self'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ]);
    }

    /** "https://{s}.tile.example.com/{z}/…" → "https://*.tile.example.com". */
    protected function origin(?string $url): string
    {
        $host = $url ? parse_url(str_replace(['{s}', '{z}', '{x}', '{y}', '{r}'], ['a', '0', '0', '0', ''], $url), PHP_URL_HOST) : null;
        if (! $host) {
            return '';
        }

        $scheme = parse_url($url, PHP_URL_SCHEME) ?: 'https';

        return str_contains($url, '{s}')
            ? $scheme.'://*.'.substr($host, strpos($host, '.') + 1)
            : $scheme.'://'.$host;
    }
}
