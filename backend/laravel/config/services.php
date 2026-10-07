<?php

return [

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'rnn' => [
        'url' => trim((string) env('API_RNN', 'http://127.0.0.1:5000/forecast/1?days=')),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'url' => rtrim((string) env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 60),
    ],

];
