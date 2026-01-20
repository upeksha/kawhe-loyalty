<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Apple Wallet Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Apple Wallet Pass Web Service and push notifications.
    |
    */

    'apple' => [
        /*
         * Web Service Authentication Token
         * 
         * This token is used to authenticate requests from Apple Wallet.
         * Set this in your .env file as APPLE_WALLET_WEB_SERVICE_AUTH_TOKEN.
         * 
         * Apple sends this in the Authorization header as:
         * Authorization: ApplePass <token>
         */
        'web_service_auth_token' => env('APPLE_WALLET_WEB_SERVICE_AUTH_TOKEN'),

        /*
         * Enable Apple Wallet Push Notifications
         * 
         * Set to true to enable APNs push notifications for pass updates.
         * Set to false to disable (useful for testing or if APNs is not configured).
         */
        'push_enabled' => env('WALLET_APPLE_PUSH_ENABLED', false),

        /*
         * Apple Push Notification Service (APNs) Configuration
         * 
         * These settings are used for token-based APNs authentication.
         * Prefer token-based auth over certificate-based auth.
         */
        'apns_key_id' => env('APPLE_APNS_KEY_ID'),
        'apns_team_id' => env('APPLE_APNS_TEAM_ID'),
        'apns_auth_key_path' => env('APPLE_APNS_AUTH_KEY_PATH'),
        'apns_topic' => env('APPLE_APNS_TOPIC', env('APPLE_PASS_TYPE_IDENTIFIER')),
        'apns_production' => env('APPLE_APNS_PRODUCTION', !env('APPLE_APNS_USE_SANDBOX', false)),
    ],
];
