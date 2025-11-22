#!/usr/bin/env php
<?php

// Debug category page URLs
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Website;
use App\Models\Folder;

if ($argc < 2) {
    echo "Usage: php debug-category-urls.php <domain> [folder_slug]\n";
    echo "Example: php debug-category-urls.php dangthanhson.com vietnam\n";
    exit(1);
}

$domain = $argv[1];
$folderSlug = $argv[2] ?? null;

echo "=== Debug Category URLs ===\n\n";

$website = Website::where('domain', $domain)->first();

if (!$website) {
    echo "❌ Website not found: $domain\n";
    exit(1);
}

echo "Website: {$website->domain} (ID: {$website->id})\n";
echo "SSL Enabled: " . ($website->ssl_enabled ? 'Yes' : 'No') . "\n\n";

$folders = Folder::where('website_id', $website->id)->get();

if ($folderSlug) {
    $folders = $folders->filter(fn($f) => $f->slug === $folderSlug);
}

foreach ($folders as $folder) {
    echo "Folder: {$folder->name} (/{$folder->slug})\n";
    echo str_repeat("=", 60) . "\n";

    $pages = $folder->pages()->orderBy('updated_at', 'desc')->get();

    echo "Pages in folder: " . $pages->count() . "\n\n";

    foreach ($pages as $page) {
        echo "  Page #{$page->id}: {$page->title}\n";
        echo "    Website ID: {$page->website_id}\n";
        echo "    Website Domain: {$page->website->domain}\n";
        echo "    Page Path: {$page->path}\n";

        // Generate URL using same logic as DeploymentService
        $pageWebsite = $page->website;

        if ($pageWebsite->id !== $website->id) {
            $protocol = $pageWebsite->ssl_enabled ? 'https://' : 'http://';
            $url = $protocol . $pageWebsite->domain . $page->path;
            echo "    ✓ Full URL (subdomain): $url\n";
        } else {
            echo "    ✓ Relative URL (same domain): {$page->path}\n";
        }

        echo "\n";
    }

    echo "\n";
}

echo "=== Debug Complete ===\n";
