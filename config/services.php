<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | Aquí configuramos credenciales de servicios externos:
    | Mailgun, Postmark, AWS, Slack, Telegram, FacKatuete, etc.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'telegram' => [
        'token'  => env('TELEGRAM_BOT_TOKEN'),
        'secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    ],

    // 🔵 FacKatuete – Facturación electrónica
    'fackatuete' => [
        // 🚪 API HTTP de FacKatuete (local en el puerto 8002 según tu organigrama)
        'base_url'    => env('FACKATUETE_BASE_URL', ''),

        // 🔐 Token Bearer generado en FacKatuete (tabla personal_access_tokens)
        'token'       => env('FACKATUETE_TOKEN'),

        // 🏢 Datos de la empresa emisora
        'empresa_ruc' => env('FACKATUETE_RUC', '80000001'),
        'empresa_dv'  => env('FACKATUETE_DV',  '0'),

        // 🌎 Ambiente SIFEN: test | prod
        'ambiente'    => env('FACKATUETE_AMBIENTE', 'test'),
    ],

];
