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
];
