<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The frontend calls this API from its own server (the /api/backend proxy),
 * so every visitor would otherwise share the frontend's IP and one rate-limit
 * bucket. The proxy sends the visitor's address in X-Client-IP together with
 * a shared secret (FRONTEND_PROXY_SECRET); only then is that address used as
 * the client IP. Without the secret both headers are ignored, so nobody can
 * pick their own IP by sending the header.
 */
class TrustFrontendClientIp
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.frontend_proxy.secret');
        $given = $request->headers->get('X-Proxy-Secret');
        $ip = $request->headers->get('X-Client-IP');

        // Never passed on, logged or trusted further down.
        $request->headers->remove('X-Proxy-Secret');
        $request->headers->remove('X-Client-IP');

        if ($secret !== '' && is_string($given) && hash_equals($secret, $given)
            && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
            // The visitor is not a trusted proxy, so Request::ip() now returns
            // this address and ignores any X-Forwarded-For.
            $request->server->set('REMOTE_ADDR', $ip);
        }

        return $next($request);
    }
}
