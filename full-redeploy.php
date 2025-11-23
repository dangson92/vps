#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Website;
use App\Models\Folder;
use App\Services\DeploymentService;

if ($argc < 2) {
    echo "Usage: php full-redeploy.php <domain>\n";
    echo "Example: php full-redeploy.php timnhakhoa.com\n";
    exit(1);
}

$domain = $argv[1];

echo "=== Full Redeploy for {$domain} ===\n\n";

$website = Website::where('domain', $domain)->first();
if (!$website) {
    echo "ERROR: Website '{$domain}' not found\n";
    exit(1);
}

$service = app(DeploymentService::class);

// Step 1: Deploy template assets
echo "1. Deploying template assets...\n";
try {
    $service->deployTemplateAssets($website, 'hotel-detail-1');
    $service->deployTemplateAssets($website, 'home-1');
    $service->deployTemplateAssets($website, 'listing-1');
    echo "   ✓ Template assets deployed\n\n";
} catch (\Exception $e) {
    echo "   ✗ Failed: " . $e->getMessage() . "\n\n";
}

// Step 2: Deploy homepage
echo "2. Deploying homepage...\n";
try {
    $service->deployLaravel1Homepage($website);
    echo "   ✓ Homepage deployed (should show 8 items)\n\n";
} catch (\Exception $e) {
    echo "   ✗ Failed: " . $e->getMessage() . "\n\n";
}

// Step 3: Deploy all category pages
echo "3. Deploying category pages...\n";
$folders = Folder::where('website_id', $website->id)->get();
foreach ($folders as $folder) {
    $path = $folder->getPath();
    echo "   - {$folder->name} ({$path})\n";
    try {
        $service->deployLaravel1CategoryPage($folder);
        echo "     ✓ Deployed\n";
    } catch (\Exception $e) {
        echo "     ✗ Failed: " . $e->getMessage() . "\n";
    }
}
echo "\n";

// Step 4: Deploy all subdomain pages
echo "4. Deploying subdomain pages (for breadcrumb updates)...\n";
$domainPattern = "%." . $domain;
$subdomains = Website::where('domain', 'like', $domainPattern)
    ->where('type', 'laravel1')
    ->where('status', 'deployed')
    ->get();

echo "   Found {$subdomains->count()} subdomains\n";
$count = 0;
foreach ($subdomains as $subdomain) {
    $pages = $subdomain->pages;
    foreach ($pages as $page) {
        echo "   - {$subdomain->domain}{$page->path}\n";
        try {
            $service->deployPage($page);
            echo "     ✓ Deployed\n";
            $count++;
        } catch (\Exception $e) {
            echo "     ✗ Failed: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n=== Summary ===\n";
echo "Main domain: {$domain}\n";
echo "Categories deployed: {$folders->count()}\n";
echo "Subdomain pages deployed: {$count}\n";
echo "\n=== Done ===\n";
echo "\nPlease check:\n";
echo "- Homepage: https://{$domain} (should show 8 items in Featured and Newest)\n";
echo "- Category: https://{$domain}/vietnam (or any category)\n";
echo "- Subdomain: (any hotel page - breadcrumb should have working links)\n";
