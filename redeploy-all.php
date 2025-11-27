#!/usr/bin/env php
<?php

// Redeploy all homepage and categories
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Website;
use App\Models\Folder;

echo "=== Redeploy All Laravel1 Websites ===\n\n";

// Dispatch all deployment jobs to queue
$websites = Website::where('type', 'laravel1')
    ->where('status', 'deployed')
    ->get();

$homepageCount = 0;
$categoryCount = 0;

foreach ($websites as $website) {
    $domainParts = explode('.', $website->domain);
    $isSubdomain = count($domainParts) > 2;

    if ($isSubdomain) continue;

    echo "Website: {$website->domain}\n";

    // Dispatch homepage deployment
    \App\Jobs\DeployLaravel1Homepage::dispatch($website->id);
    $homepageCount++;
    echo "  ✓ Homepage deployment queued\n";

    // Dispatch category deployments
    $folders = Folder::where('website_id', $website->id)->get();
    foreach ($folders as $folder) {
        \App\Jobs\DeployLaravel1CategoryPage::dispatch($folder->id);
        $categoryCount++;
        echo "  ✓ Category '{$folder->name}' deployment queued\n";
    }

    echo "\n";
}

echo "=== Queued for Deployment ===\n";
echo "Homepages: {$homepageCount}\n";
echo "Categories: {$categoryCount}\n\n";

echo "Queue worker will process these jobs.\n";
echo "Monitor: sudo journalctl -u vps-queue-worker -f\n\n";

// Check queue status
try {
    $pending = DB::table('jobs')->count();
    echo "Current pending jobs in queue: {$pending}\n";
} catch (\Exception $e) {
    echo "Cannot check queue: " . $e->getMessage() . "\n";
}

echo "\n=== Done ===\n";
