<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PrivacyHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        return self::apply($response);
    }

    public static function apply($response)
    {
        $response->headers->set('Cache-Control', 'no-store, private, max-age=0');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
        return $response;
    }
}
