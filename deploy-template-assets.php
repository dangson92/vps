#!/usr/bin/php
<?php

// Deploy template assets (CSS/JS)
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Website;
use App\Services\DeploymentService;

echo "=== Deploy Template Assets ===\n\n";

$deploymentService = app(DeploymentService::class);

$websites = Website::where('type', 'laravel1')
    ->where('status', 'deployed')
    ->get();

$deployed = 0;

foreach ($websites as $website) {
    $domainParts = explode('.', $website->domain);
    $isSubdomain = count($domainParts) > 2;

    if ($isSubdomain) continue; // Only main domains need template assets

    echo "Website: {$website->domain}\n";

    $templates = ['home-1', 'listing-1', 'hotel-detail-1'];

    foreach ($templates as $template) {
        echo "  Deploying {$template} assets... ";
        try {
            $deploymentService->deployTemplateAssets($website, $template);
            echo "✓\n";
            $deployed++;
        } catch (\Exception $e) {
            echo "✗ Error: " . $e->getMessage() . "\n";
        }
    }

    echo "\n";
}

echo "=== Complete ===\n";
echo "Deployed {$deployed} template assets\n";
