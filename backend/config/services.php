<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Meta Conversions API
    |--------------------------------------------------------------------------
    |
    | Server-side conversion reporting. The access token is a credential: it
    | belongs here, read from the environment, and must never reach a PUBLIC_*
    | variable — those are compiled into the browser bundle, where anyone
    | could read it and send fabricated conversions into the ad account.
    |
    | Leave the token blank to switch the integration off; the job then does
    | nothing rather than failing on every lead.
    |
    */

    'meta' => [
        'pixel_id' => env('META_PIXEL_ID'),
        'access_token' => env('META_CAPI_ACCESS_TOKEN'),
        'graph_version' => env('META_GRAPH_VERSION', 'v21.0'),
        // Set while validating in Events Manager -> Test Events, then remove.
        // Events carrying this code are excluded from ad optimisation.
        'test_event_code' => env('META_CAPI_TEST_EVENT_CODE'),
        'timeout' => (int) env('META_CAPI_TIMEOUT', 8),
    ],

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

];
