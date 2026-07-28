<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyN8nBackupSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('backup.n8n_secret');
        $header = $request->header('X-N8N-Backup-Secret');

        if (! $secret || ! hash_equals($secret, (string) $header)) {
            abort(403, 'Invalid backup secret.');
        }

        return $next($request);
    }
}
