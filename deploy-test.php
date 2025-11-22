#!/usr/bin/env php
<?php

// Manual deployment test script
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Website;
use App\Models\Folder;
use App\Services\DeploymentService;

echo "=== VPS Manager Deployment Test ===\n\n";

$deploymentService = app(DeploymentService::class);

// Get all laravel1 websites
$websites = Website::where('type', 'laravel1')
    ->where('status', 'deployed')
    ->get();

echo "Found " . $websites->count() . " laravel1 websites\n\n";

foreach ($websites as $website) {
    $domainParts = explode('.', $website->domain);
    $isSubdomain = count($domainParts) > 2;

    echo "Website: {$website->domain} (ID: {$website->id})\n";
    echo "  Type: " . ($isSubdomain ? "Subdomain" : "Main Domain") . "\n";

    if ($isSubdomain) {
        // Deploy subdomain pages
        $pages = $website->pages;
        echo "  Pages: " . $pages->count() . "\n";

        foreach ($pages as $page) {
            echo "  Deploying page: {$page->path}\n";
            try {
                $deploymentService->deployPage($page);
                echo "    ✓ Success\n";
            } catch (\Exception $e) {
                echo "    ✗ Error: " . $e->getMessage() . "\n";
            }
        }
    } else {
        // Deploy homepage and categories
        echo "  Deploying homepage...\n";
        try {
            $deploymentService->deployLaravel1Homepage($website);
            echo "    ✓ Success\n";
        } catch (\Exception $e) {
            echo "    ✗ Error: " . $e->getMessage() . "\n";
        }

        $folders = Folder::where('website_id', $website->id)->get();
        echo "  Folders: " . $folders->count() . "\n";

        foreach ($folders as $folder) {
            echo "  Deploying category: {$folder->slug} (Pages: {$folder->pages()->count()})\n";
            try {
                $deploymentService->deployLaravel1CategoryPage($folder);
                echo "    ✓ Success\n";
            } catch (\Exception $e) {
                echo "    ✗ Error: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "\n";
}

echo "=== Deployment Test Complete ===\n";
