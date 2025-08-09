<?php

namespace App\Http\Middleware;

use Closure;

class CorsMiddleware
{
    public function handle($request, Closure $next)
    {
        $allowedOrigins = [
            'http://localhost:3000',
            'https://deyna.store',
        ];

        $origin = $request->headers->get('Origin');
        $allowOriginHeader = in_array($origin, $allowedOrigins) ? $origin : $allowedOrigins[0];

        if ($request->getMethod() === 'OPTIONS') {
            return response('', 204)->withHeaders([
                'Access-Control-Allow-Origin' => $allowOriginHeader,
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
                'Access-Control-Allow-Credentials' => 'true',
            ]);
        }

        return $next($request)->withHeaders([
            'Access-Control-Allow-Origin' => $allowOriginHeader,
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
            'Access-Control-Allow-Credentials' => 'true',
        ]);
    }
}
