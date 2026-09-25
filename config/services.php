<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
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

    'virustotal' => [
        'key' => env('VIRUSTOTAL_API_KEY'),
        'timeout' => (int) env('VIRUSTOTAL_TIMEOUT', 3),
        // Tope de links por análisis: el plan gratuito de VirusTotal permite 4 consultas por minuto.
        'max_urls' => (int) env('VIRUSTOTAL_MAX_URLS', 2),
        'cache_ttl' => (int) env('VIRUSTOTAL_CACHE_TTL', 3600),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-flash-lite-latest'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 15
        ),
    ],

];
