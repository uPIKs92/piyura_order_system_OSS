<?php

return [
    'trusted_proxies' => env('TRUSTED_PROXIES'),
    'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
    'csp' => [
        'default_src' => ["'self'"],
        'script_src' => ["'self'"],
        'style_src' => ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'],
        'img_src' => ["'self'", 'data:', 'https:'],
        'font_src' => ["'self'", 'data:', 'https://fonts.gstatic.com'],
        'connect_src' => ["'self'"],
        'object_src' => ["'none'"],
        'base_uri' => ["'self'"],
        'form_action' => ["'self'"],
        'frame_ancestors' => ["'self'"],
    ],
];
