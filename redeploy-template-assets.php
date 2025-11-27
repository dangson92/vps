#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Website;
use App\Services\DeploymentService;

if ($argc < 2) {
    echo "Usage: php redeploy-template-assets.php <template-name> [domain]\n";
    echo "Example: php redeploy-template-assets.php hotel-detail-1\n";
    echo "Example: php redeploy-template-assets.php hotel-detail-1 timnhakhoa.com\n";
    exit(1);
}

$templateName = $argv[1];
$domain = $argv[2] ?? null;

echo "=== Redeploy Template Assets: {$templateName} ===\n\n";

$service = app(DeploymentService::class);

if ($domain) {
    // Deploy for specific website
    $website = Website::where('domain', $domain)->first();
    if (!$website) {
        echo "ERROR: Website '{$domain}' not found\n";
        exit(1);
    }

    echo "Deploying assets for: {$website->domain}\n";
    try {
        $service->deployTemplateAssets($website, $templateName);
        echo "✓ Assets deployed successfully\n";
    } catch (\Exception $e) {
        echo "✗ Failed: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    // Deploy for all Laravel1 websites
    $websites = Website::where('type', 'laravel1')
        ->where('status', 'deployed')
        ->get();

    echo "Found {$websites->count()} Laravel1 websites\n\n";

    foreach ($websites as $website) {
        echo "Deploying assets for: {$website->domain}\n";
        try {
            $service->deployTemplateAssets($website, $templateName);
            echo "  ✓ Success\n";
        } catch (\Exception $e) {
            echo "  ✗ Failed: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n=== Done ===\n";
