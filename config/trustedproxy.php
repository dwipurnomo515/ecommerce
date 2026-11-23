<?php

return [

    /*
    | Behind an HTTPS proxy (Caddy, Cloudflare, a load balancer) every
    | request arrives FROM the proxy. Without trusting it, request()->ip()
    | is the proxy for everyone, so one visitor's failed logins or write
    | throttle would lock out all of them, and URLs would say http://.
    |
    | Only the listed addresses (comma-separated, CIDR allowed) may set
    | X-Forwarded-For/-Proto: anyone else could otherwise claim any IP and
    | dodge the throttles. Unset (the default, and in dev): trust nobody.
    |
    | Laravel's TrustProxies middleware reads this key by itself. It lives
    | in config (not bootstrap/app.php) so it survives config:cache.
    */
    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
