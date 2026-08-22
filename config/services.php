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

    'rickandmorty' => [
        'base_url' => env('RICKANDMORTY_BASE_URL', 'https://rickandmortyapi.com/api'),
        // Segundos máximos esperando una respuesta.
        'timeout' => (int) env('RICKANDMORTY_TIMEOUT', 10),
        // Intentos totales por petición ante errores de red, 429 o 5xx.
        'max_attempts' => (int) env('RICKANDMORTY_MAX_ATTEMPTS', 3),
        // Espera base entre intentos; se multiplica por el número de intento.
        'retry_delay_ms' => (int) env('RICKANDMORTY_RETRY_DELAY_MS', 500),
    ],

];
