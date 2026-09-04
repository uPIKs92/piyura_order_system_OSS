<?php

$baseDomain = env('TENANT_BASE_DOMAIN');

// Match any tenant subdomain of the base domain (e.g. *.piyuralabs.com) over
// https, with or without a port. Skipped in local dev where base domain is null.
$patterns = [];
if (is_string($baseDomain) && $baseDomain !== '') {
    $patterns[] = '/^https:\/\/[a-z0-9-]+\.'.preg_quote($baseDomain, '/').'(?::\d+)?$/i';
}

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Explicit origins: the app/marketing URL always, plus local dev hosts
    // only in non-production environments. env() is used because config files
    // load before the "env" container binding exists.
    'allowed_origins' => array_values(array_filter([
        rtrim((string) env('APP_URL', ''), '/'),
        ...(in_array(env('APP_ENV', 'production'), ['local', 'testing', 'dev'], true) ? [
            'http://localhost:5173',
            'http://localhost:8084',
            'http://127.0.0.1:5173',
            'http://127.0.0.1:8084',
        ] : []),
    ])),

    // Dynamic: any tenant subdomain of the configured base domain.
    'allowed_origins_patterns' => $patterns,

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Required so the SPA can send credentials (session cookies).
    'supports_credentials' => true,

];
