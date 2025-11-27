#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Website;
use App\Models\Folder;

if ($argc < 2) {
    echo "Usage: php redeploy-nested-folders.php <domain> [folder_slug]\n";
    echo "Example: php redeploy-nested-folders.php timnhakhoa.com\n";
    echo "Example: php redeploy-nested-folders.php timnhakhoa.com vietnam/halong\n";
    exit(1);
}

$domain = $argv[1];
$specificSlug = $argv[2] ?? null;

echo "=== Redeploy Nested Folders ===\n\n";

$website = Website::where('domain', $domain)->first();

if (!$website) {
    echo "ERROR: Website '{$domain}' not found\n";
    exit(1);
}

echo "Website: {$website->domain} (ID: {$website->id})\n\n";

// Get all folders with parent_id (nested folders)
$query = Folder::where('website_id', $website->id);

if ($specificSlug) {
    // Parse nested slug like "vietnam/halong"
    $parts = explode('/', $specificSlug);
    $slug = array_pop($parts);
    $query->where('slug', $slug);
}

$folders = $query->get();

if ($folders->isEmpty()) {
    echo "No folders found\n";
    exit(0);
}

$service = app(App\Services\DeploymentService::class);

foreach ($folders as $folder) {
    $path = $folder->getPath();
    echo "Deploying: {$folder->name}\n";
    echo "  Slug: {$folder->slug}\n";
    echo "  Full path: {$path}\n";
    echo "  Parent: " . ($folder->parent_id ? "#{$folder->parent_id}" : "none") . "\n";

    try {
        $service->deployLaravel1CategoryPage($folder);
        echo "  ✓ Deployed successfully\n\n";
    } catch (\Exception $e) {
        echo "  ✗ Failed: " . $e->getMessage() . "\n\n";
    }
}

echo "=== Done ===\n";
