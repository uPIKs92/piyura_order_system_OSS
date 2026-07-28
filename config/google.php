<?php

return [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect_uri' => env('GOOGLE_REDIRECT_URI', rtrim((string) env('APP_URL', ''), '/').'/api/integrations/google/callback'),
    'scopes' => [
        'https://www.googleapis.com/auth/spreadsheets',
        'https://www.googleapis.com/auth/userinfo.email',
    ],
    'retry_attempts' => env('SHEETS_RETRY_ATTEMPTS', env('N8N_RETRY_ATTEMPTS', 3)),
    'batch_size' => env('SHEETS_BATCH_SIZE', env('N8N_BATCH_SIZE', 50)),
    'telegram_bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'telegram_chat_id' => env('TELEGRAM_CHAT_ID'),
    'defaults' => [
        'products_tab' => 'Products',
        'orders_tab' => 'Orders',
        'reporting_tab' => 'Reporting',
    ],
];
