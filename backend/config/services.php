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

    /*
     * "Terbitkan ulang situs" in the admin: runs the frontend deploy workflow
     * so landing-page prices follow the CMS. A fine-grained GitHub token with
     * Actions: read and write on that repository only.
     */
    'site_deploy' => [
        'repo' => env('SITE_DEPLOY_REPO'),
        'token' => env('SITE_DEPLOY_TOKEN'),
        'workflow' => env('SITE_DEPLOY_WORKFLOW', 'deploy.yml'),
        'ref' => env('SITE_DEPLOY_REF', 'main'),
    ],

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
    | Scalev Orders API
    |--------------------------------------------------------------------------
    |
    | Read-only pull of paid orders for the sales popup and buyer counter.
    | The API key is a credential: it stays server-side and is never
    | compiled into the frontend bundle. Leave the key blank to switch
    | the sync off; the command then does nothing rather than failing.
    |
    | Reference: https://dev.scalev.com/reference/listorders.md
    |
    */

    'scalev' => [
        'api_key' => env('SCALEV_API_KEY'),
        'api_url' => env('SCALEV_API_URL', 'https://api.scalev.com'),
        'timeout' => (int) env('SCALEV_TIMEOUT', 15),

        // Which Scalev product names belong to each sales page, so the popup
        // on the AC page never shows someone who bought the inspection class.
        // Matched as a case-insensitive "contains" against the variant name
        // stored in scalev_purchases.product_name.
        'products' => [
            'inspeksi' => ['Inspeksi'],
            'ac' => ['AC Mobil'],
        ],
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
