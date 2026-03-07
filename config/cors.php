<?php

/**
 * CORS configuration for the BeyondChats API.
 *
 * Allows the React frontend (localhost:5173) to make
 * cross-origin requests to the Laravel API (localhost:8000).
 */
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:5173'),
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
