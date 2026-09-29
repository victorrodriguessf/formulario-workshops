<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (method_exists($response, 'header')) {
            $response->header('X-Frame-Options', 'SAMEORIGIN');
            $response->header('X-Content-Type-Options', 'nosniff');
            $response->header('X-XSS-Protection', '0');
            $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
            $response->header('Permissions-Policy', 'camera=(), geolocation=(), microphone=()');
            $assetSources = "'self'";
            $connectSources = "'self'";
            $styleSources = "'self'";

            if (app()->environment('local')) {
                $assetSources .= ' http://127.0.0.1:5173';
                $connectSources .= ' http://127.0.0.1:5173 ws://127.0.0.1:5173';
                $styleSources .= " http://127.0.0.1:5173 'unsafe-inline'";
            }

            $response->header('Content-Security-Policy', "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'; img-src {$assetSources} data: https:; style-src {$styleSources}; script-src {$assetSources}; font-src {$assetSources}; connect-src {$connectSources};");

            if (app()->isProduction() && $request->isSecure()) {
                $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            }

            if ($request->is('admin/*') || $request->routeIs('events.register.status', 'events.register.status.check')) {
                $response->header('Cache-Control', 'no-store, private');
                $response->header('Pragma', 'no-cache');
            }
        }

        return $response;
    }
}
