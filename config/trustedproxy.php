<?php

return [
    /*
     * Reverse proxies in front of this API (e.g. "127.0.0.1" for a local
     * nginx), comma-separated, or "*" for the calling address. Requests from
     * these may set X-Forwarded-For. Empty: no proxy is trusted.
     *
     * The frontend's server is not listed here: it proves itself with
     * FRONTEND_PROXY_SECRET instead (App\Http\Middleware\TrustFrontendClientIp).
     */
    'proxies' => env('TRUSTED_PROXIES'),
];
