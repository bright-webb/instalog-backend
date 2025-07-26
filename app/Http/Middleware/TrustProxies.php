<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Middleware\TrustProxies as Middleware;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * Use '*' to trust all proxies (e.g., Cloudflare, Load Balancer).
     */
    protected array|string|null $proxies = '*';

    /**
     * The headers that should be used to detect proxies.
     */
    protected int $headers = Request::HEADER_X_FORWARDED_ALL;
}
