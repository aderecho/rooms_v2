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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT'),
    ],

    'saml' => [
        'sp_entity_id' => env('SAML_SP_ENTITY_ID', env('APP_URL') ? env('APP_URL') . '/saml2/metadata' : null),
        'idp_entity_id' => env('SAML_IDP_ENTITY_ID'),
        'idp_public_cert' => env('SAML_IDP_PUBLIC_CERT'),
        'idp_private_key' => env('SAML_IDP_PRIVATE_KEY'),
        'idp_key_passphrase' => env('SAML_IDP_KEY_PASSPHRASE'),
        'assertion_ttl_seconds' => (int) env('SAML_ASSERTION_TTL_SECONDS', 300),
        'clock_skew_seconds' => (int) env('SAML_CLOCK_SKEW_SECONDS', 60),
        'require_signed_requests' => (bool) env('SAML_REQUIRE_SIGNED_REQUESTS', false),
        'sign_responses' => (bool) env('SAML_SIGN_RESPONSES', true),
        'sign_assertions' => (bool) env('SAML_SIGN_ASSERTIONS', true),
    ],

];
