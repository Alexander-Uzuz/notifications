<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Notification Channel
    |--------------------------------------------------------------------------
    |
    | Channel used by the Notifier facade when no explicit channel is given
    | via ->channel('name'). Only "telegram" is supported in v1.
    |
    */

    'default' => env('NOTIFICATIONS_DEFAULT_CHANNEL', 'telegram'),

    /*
    |--------------------------------------------------------------------------
    | Queue Settings
    |--------------------------------------------------------------------------
    |
    | Notifier::send() dispatches notifications through the queue by default.
    | Set enabled=false to force synchronous delivery for every call.
    |
    */

    'queue' => [
        'enabled' => env('NOTIFICATIONS_QUEUE_ENABLED', true),
        'connection' => env('NOTIFICATIONS_QUEUE_CONNECTION'),
        'name' => env('NOTIFICATIONS_QUEUE_NAME', 'notifications'),
        'tries' => (int) env('NOTIFICATIONS_QUEUE_TRIES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Channels
    |--------------------------------------------------------------------------
    */

    'channels' => [
        'telegram' => [
            'bot_token' => env('TELEGRAM_BOT_TOKEN'),
            'chat_id' => env('TELEGRAM_CHAT_ID'),
            'parse_mode' => env('TELEGRAM_PARSE_MODE', 'HTML'),
        ],
    ],
];
