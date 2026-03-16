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
    'schema' => env('TUYA_SCHEMA', 'smartlife'),

    /*
    | QR login token API path. If you get 1108 "uri path invalid", check your
    | Tuya project API list and set TUYA_QR_TOKEN_PATH to the exact path.
    | Common variants: .../authorized-login:token or .../authorized-login-token
    */
    'qr_token_path' => env('TUYA_QR_TOKEN_PATH', '/v1.0/iot-01/associated-users/actions/authorized-login:token'),

    /*
    | Device Sharing (Home Assistant style) configuration
    */
    'sharing' => [
        'client_id' => env('TUYA_SHARING_CLIENT_ID'),
        'schema' => env('TUYA_SHARING_SCHEMA', 'haauthorize'),
        'service_url' => env('TUYA_SHARING_SERVICE_URL', 'http://tuya-sharing:8000'),
        'qr_payload_prefix' => env('TUYA_SHARING_QR_PREFIX', 'tuyaSmart--qrLogin?token='),
    ],
];
