<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tuya API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Tuya IoT platform API integration.
    |
    */

    'client_id' => env('TUYA_CLIENT_ID'),
    'client_secret' => env('TUYA_CLIENT_SECRET'),
    'base_url' => env('TUYA_BASE_URL', 'https://openapi.tuyaus.com'),

    'oauth_redirect_uri' => env('TUYA_OAUTH_REDIRECT_URI'),

    /*
     * OAuth 2.0 authorization page URL (where the user is sent to log in and authorize).
     * If you get 404 from Tuya, this URL may have changed: check your project at
     * https://iot.tuya.com → Cloud → your project → Devices → Link App Account
     * → Configure OAuth 2.0 Authorization. Use the authorization / H5 page URL shown there,
     * or try: https://openapi.tuyaus.com/oauth/authorize (alternative path).
     */
    'oauth_authorize_url' => env('TUYA_OAUTH_AUTHORIZE_URL') ?: 'https://openapi.tuyaus.com/login.action',
];
