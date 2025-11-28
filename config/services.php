<?php

return [
    'cloudflare' => [
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
        'zone_id' => env('CLOUDFLARE_ZONE_ID'),
    ],
    
    'ssl' => [
        'email' => env('SSL_EMAIL', 'admin@localhost'),
    ],

    'worker' => [
        'port' => env('WORKER_PORT', 8080),
        'ssl_generate_endpoints' => [
            '/api/generate-ssl',
            '/api/generate_ssl',
            '/api/ssl/generate',
        ],
        'ssl_revoke_endpoints' => [
            '/api/revoke-ssl',
            '/api/revoke_ssl',
            '/api/ssl/revoke',
        ],
    ],
];
