<?php

return [
    'cloudflare' => [
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
        'zone_id' => env('CLOUDFLARE_ZONE_ID'),
    ],
    
    'ssl' => [
        'email' => env('SSL_EMAIL', 'admin@localhost'),
    ],
];