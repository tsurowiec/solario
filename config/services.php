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

    'tauron' => [
        'username' => env('TAURON_USERNAME'),
        'password' => env('TAURON_PASSWORD'),
        'site' => env('TAURON_SITE'), // optional metering point id
        'lookback_days' => env('TAURON_LOOKBACK_DAYS', 7),
    ],

    // Location of the PV installation, for sunrise / sunset when estimating a partial day's production.
    'pv' => [
        'latitude' => (float) env('PV_LATITUDE', 50.06),
        'longitude' => (float) env('PV_LONGITUDE', 19.94),
    ],

];
