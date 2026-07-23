<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanonicalHost
{
    private const LOCAL_ALIASES = ['localhost', '127.0.0.1', '[::1]', '::1'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('local') || app()->runningInConsole()) {
            return $next($request);
        }

        $canonicalHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! is_string($canonicalHost) || $canonicalHost === '') {
            return $next($request);
        }

        $requestHost = $request->getHost();

        $isAliasMismatch = in_array($requestHost, self::LOCAL_ALIASES, true)
            && in_array($canonicalHost, self::LOCAL_ALIASES, true)
            && $requestHost !== $canonicalHost;

        if (! $isAliasMismatch) {
            return $next($request);
        }

        $url = $request->getScheme().'://'.$canonicalHost;
        $port = $request->getPort();

        if ($port && ! in_array($port, [80, 443], true)) {
            $url .= ':'.$port;
        }

        return redirect()->to($url.$request->getRequestUri());
    }
}
