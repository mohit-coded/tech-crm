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

    'twilio' => [
        'sid' => env('TWILIO_ACCOUNT_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'phone_number' => env('TWILIO_PHONE_NUMBER'),
    ],

    'facebook_ads' => [
        'client_id' => env('FACEBOOK_ADS_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_ADS_CLIENT_SECRET'),
        // Intentionally the SAME env var as client_secret above, not a
        // second one — in Facebook's own system there is only one "App
        // Secret" credential per app, used both for the OAuth token
        // exchange (as client_secret) and for signing webhook payloads
        // (what X-Hub-Signature-256 is computed from). A separate
        // FACEBOOK_ADS_APP_SECRET env var would just be a second place
        // for the identical real-world value to be set, with nothing to
        // stop the two drifting out of sync. Still given its own config
        // KEY, though, rather than reading services.facebook_ads.client_secret
        // directly at the signature-verification call site — 'app_secret'
        // names what that usage actually is (see VerifyFacebookSignature),
        // even though the underlying value is shared.
        'app_secret' => env('FACEBOOK_ADS_CLIENT_SECRET'),
        // Not a secret from Facebook — a value THIS app invents and sets
        // in both places: here, and in the Facebook App dashboard's
        // webhook subscription config. Facebook echoes it back in the
        // one-time GET verification handshake so we can confirm it's
        // really our own webhook being configured.
        'webhook_verify_token' => env('FACEBOOK_ADS_WEBHOOK_VERIFY_TOKEN'),
    ],

    'google_business' => [
        'client_id' => env('GOOGLE_BUSINESS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_BUSINESS_CLIENT_SECRET'),
    ],

];
