<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, SparkPost and others. This file provides a sane default
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'jellyfin' => [
        'webhook_secret' => env('JELLYFIN_WEBHOOK_SECRET'),
    ],

    /*
    | Sign-in through bspdx.com's OAuth 2.0 (Passport) server. The authorize,
    | token and userinfo addresses are derived from base_url, which must be
    | HTTPS. `scopes` is space-separated; bspdx.com defines `userinfo`.
    */
    'bspdx' => [
        'enabled' => (bool) env('BSPDX_LOGIN_ENABLED', false),
        'base_url' => env('BSPDX_BASE_URL', 'https://bspdx.com'),
        'client_id' => env('BSPDX_CLIENT_ID'),
        'client_secret' => env('BSPDX_CLIENT_SECRET'),
        'redirect' => env('BSPDX_REDIRECT_URI'),
        'scopes' => env('BSPDX_SCOPES', 'userinfo'),
        'timeout' => (int) env('BSPDX_TIMEOUT', 10),
    ],

];
