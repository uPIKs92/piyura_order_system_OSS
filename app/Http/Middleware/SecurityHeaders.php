<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $nonce);
        view()->share('cspNonce', $nonce);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // geolocation=self: the delivery map picker uses the Geolocation API
        // from the top-level document; `geolocation=()` disabled the API
        // entirely (instant PERMISSION_DENIED, no browser prompt ever shown,
        // manual grants ignored). Same-origin only keeps it restrictive.
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=self');

        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age='.config('security.hsts_max_age').'; includeSubDomains'
            );
        }

        $cspConfig = config('security.csp', []);
        $cspConfig['script_src'] = array_merge(
            $cspConfig['script_src'] ?? [],
            ["'nonce-{$nonce}'"],
        );

        $csp = collect($cspConfig)
            ->map(fn (array $values, string $directive) => str_replace('_', '-', $directive).' '.implode(' ', $values))
            ->implode('; ');

        if ($csp !== '') {
            $response->headers->set('Content-Security-Policy', $csp);
        }

        return $response;
    }
}
