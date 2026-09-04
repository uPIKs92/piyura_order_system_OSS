<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict the Mayar webhook endpoint to Mayar's known egress IPs.
 *
 * Mayar webhooks carry no signature header, so source IP allowlisting is the
 * primary trust boundary. An empty allowlist fails closed (403) — production
 * MUST configure MAYAR_WEBHOOK_IP_ALLOWLIST or the webhook cannot fire.
 */
class VerifyMayarWebhookIp
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowlist = array_filter(
            array_map('trim', explode(',', (string) config('services.mayar.webhook_ip_allowlist', '')))
        );

        $ip = $request->ip();

        // Fail closed: an empty allowlist means the webhook is unauthenticated
        // and accepts forged payment notifications from anyone. Production MUST
        // configure MAYAR_WEBHOOK_IP_ALLOWLIST. Sandbox/dev that genuinely needs
        // an open webhook can set the allowlist explicitly or disable Mayar.
        if (empty($allowlist)) {
            Log::error('Mayar webhook rejected — IP allowlist not configured.', [
                'ip' => $ip,
            ]);

            abort(403, 'Webhook IP allowlist is not configured.');
        }

        if (! in_array($ip, $allowlist, true)) {
            abort(403, 'Unauthorized webhook source.');
        }

        return $next($request);
    }
}
