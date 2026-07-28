<?php

return [
    'webhook_url' => env('N8N_WEBHOOK_URL'),
    'retry_attempts' => env('N8N_RETRY_ATTEMPTS', 3),
    'retry_delay_ms' => env('N8N_RETRY_DELAY_MS', 1000),
    'batch_size' => env('N8N_BATCH_SIZE', 50),
    'telegram_bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'telegram_chat_id' => env('TELEGRAM_CHAT_ID'),
];
