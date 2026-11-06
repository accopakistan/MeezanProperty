<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses any request whose Host is not this site's, before anything can build
 * a link from it. A faked Host would otherwise reach canonical tags and put
 * password-reset links, and so the victim's token, on the attacker's site.
 *
 * Laravel's own TrustHosts does nothing in local and testing, so a mistake here
 * would only show up in production. This one applies everywhere.
 */
class TrustAppHostsOnly
{
    private const LOCAL_HOSTS = ['localhost', '127.0.0.1', '[::1]'];

    public function handle(Request $request, Closure $next): Response
    {
        $hosts = $this->trustedHosts();

        // An empty list would switch Symfony's check off, so no usable APP_URL refuses everything.
        if ($hosts === []) {
            return $this->refuse();
        }

        Request::setTrustedHosts(array_map(fn (string $host) => '^'.preg_quote($host).'$', $hosts));

        try {
            $host = $request->getHost();
        } catch (SuspiciousOperationException) {
            return $this->refuse();
        }

        // One site, one address: www.meezanproperty.com moves permanently to meezanproperty.com
        // (or the other way round, whichever APP_URL names).
        $canonical = parse_url((string) config('app.url'), PHP_URL_HOST);
        if ($canonical && strcasecmp($host, $canonical) !== 0 && strcasecmp(preg_replace('/^www\./i', '', $host), preg_replace('/^www\./i', '', $canonical)) === 0
            && $request->isMethodSafe()) {
            $base = rtrim((string) config('app.url'), '/');
            $path = $request->getRequestUri();

            return redirect()->away(preg_replace('#^(https?://[^/]+).*$#i', '$1', $base).$path, Response::HTTP_MOVED_PERMANENTLY);
        }

        return $next($request);
    }

    /**
     * The APP_URL host in its bare and www forms, plus local addresses during development.
     *
     * @return list<string>
     */
    private function trustedHosts(): array
    {
        $hosts = app()->environment('local', 'testing') ? self::LOCAL_HOSTS : [];

        if ($host = parse_url((string) config('app.url'), PHP_URL_HOST)) {
            $bare = preg_replace('/^www\./i', '', $host);

            array_push($hosts, $bare, 'www.'.$bare);
        }

        return $hosts;
    }

    /**
     * Plain text on purpose: an error page would build its links from the request.
     */
    private function refuse(): Response
    {
        return response('Bad Request', Response::HTTP_BAD_REQUEST, ['Content-Type' => 'text/plain']);
    }
}
