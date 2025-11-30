<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'blockchain' => [
        'enabled' => env('BLOCKCHAIN_ENABLED', false), // Set to false for testing
        'api_url' => env('BLOCKCHAIN_API_URL', 'http://localhost:3000/api'),
        'timeout' => env('BLOCKCHAIN_API_TIMEOUT', 30),
        'retry_times' => env('BLOCKCHAIN_API_RETRY_TIMES', 3),
        'cache_ttl' => env('BLOCKCHAIN_CACHE_TTL', 300), // 5 minutes
    ],

];
