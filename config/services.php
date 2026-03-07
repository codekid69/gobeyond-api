<?php

/**
 * Google API configuration for BeyondChats.
 *
 * These values are read from the .env file and used by GmailService.
 * Add this to config/services.php in a full Laravel install.
 */
return [
    // ... other services

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI', 'http://localhost:8000/api/gmail-callback'),
    ],
];
