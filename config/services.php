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

    'resend' => [
        'key' => env('RESEND_KEY'),
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
    'egosms' => [
        'username' => env('EGOSMS_USERNAME'),
        'password' => env('EGOSMS_PASSWORD'),
        'sender' => env('EGOSMS_SENDER', 'SuperSpot Wifi'),
        'api_url' => env('EGOSMS_API_URL', 'https://www.egosms.co/api/v1/plain/'),
    ],
    'cinemaug' => [
        'token' => env('CINEMAUG_API_TOKEN'),
        'api_url' => env('CINEMAUG_API_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the active payment gateway. Supported: 'yopayments', 'cinemaug'
    | Defaults to 'yopayments' if not specified.
    |
    */
    'payment_gateway' => env('PAYMENT_GATEWAY', 'yopayments'),
    'payment_gateway_auto_switch' => env('PAYMENT_GATEWAY_AUTO_SWITCH', false),
    'payment_gateway_switch_every' => (int) env('PAYMENT_GATEWAY_SWITCH_EVERY', 10),

    'yopayments' => [
        'username' => env('YOPAYMENTS_API_USERNAME'),
        'password' => env('YOPAYMENTS_API_PASSWORD'),
        'api_url' => env('YOPAYMENTS_API_URL', 'https://paymentsapi1.yo.co.ug/ybs/task.php'),
        'narrative' => env('YOPAYMENTS_NARRATIVE', 'SuperSpot WiFi Payment'),
    ],
];
