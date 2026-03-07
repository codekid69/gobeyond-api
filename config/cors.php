<?php

/**
 * CORS configuration for the BeyondChats API.
 *
 * Allows the React frontend (any dev port or production URL)
 * to make cross-origin requests to the Laravel API.
 */
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_unique(array_filter([
        env('FRONTEND_URL', 'http://localhost:5173'),
        'http://localhost:5173',
        'http://localhost:5174',
        'http://localhost:5175',
    ]))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
