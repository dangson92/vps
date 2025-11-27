#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Website;
use App\Models\Page;
use App\Services\DeploymentService;

if ($argc < 2) {
    echo "Usage: php redeploy-pages.php <domain>\n";
    echo "Example: php redeploy-pages.php timnhakhoa.com\n";
    exit(1);
}

$domain = $argv[1];

echo "=== Redeploy All Pages: {$domain} ===\n\n";

$website = Website::where('domain', $domain)->first();
if (!$website) {
    echo "ERROR: Website '{$domain}' not found\n";
    exit(1);
}

echo "Website: {$website->domain} (ID: {$website->id})\n\n";

$service = app(DeploymentService::class);

// Get all subdomain pages
$domainPattern = "%." . $domain;
$subdomains = Website::where('domain', 'like', $domainPattern)
    ->where('type', 'laravel1')
    ->where('status', 'deployed')
    ->get();

echo "Found {$subdomains->count()} subdomains\n\n";

$successCount = 0;
$errorCount = 0;

foreach ($subdomains as $subdomain) {
    $pages = Page::where('website_id', $subdomain->id)->get();

    foreach ($pages as $page) {
        echo "Deploying: {$subdomain->domain}{$page->path}\n";
        try {
            $service->deployPage($page);
            echo "  ✓ Success\n";
            $successCount++;
        } catch (\Exception $e) {
            echo "  ✗ Failed: " . $e->getMessage() . "\n";
            $errorCount++;
        }
    }
}

echo "\n=== Summary ===\n";
echo "Success: {$successCount}\n";
echo "Failed: {$errorCount}\n";
echo "\n=== Done ===\n";
