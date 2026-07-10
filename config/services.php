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

    /*
    |--------------------------------------------------------------------------
    | WhatsApp (Twilio)
    |--------------------------------------------------------------------------
    | Para recordatorios y notificaciones. Número "from" debe ser el de Twilio
    | (sandbox ej. +14155238886). Destinos con código país, ej. +5491112345678.
    */
    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM', ''), // ej. +14155238886 (sin prefijo whatsapp:)
        'admin_whatsapp_numbers' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('WHATSAPP_ADMIN_NUMBERS', ''))
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Flat.io — editor de partituras embebido
    |--------------------------------------------------------------------------
    | En localhost funciona sin App ID. En producción: https://flat.io/developers/apps
    */
    'flat' => [
        'embed_app_id' => env('FLAT_EMBED_APP_ID', ''),
    ],

];
