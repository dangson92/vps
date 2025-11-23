#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Website;
use App\Models\Folder;

if ($argc < 2) {
    echo "Usage: php check-homepage-data.php <domain>\n";
    exit(1);
}

$domain = $argv[1];

echo "=== Homepage Data Check: {$domain} ===\n\n";

$website = Website::where('domain', $domain)->first();
if (!$website) {
    echo "ERROR: Website not found\n";
    exit(1);
}

echo "Website: {$website->domain} (ID: {$website->id})\n\n";

// Check folders
$folders = Folder::where('website_id', $website->id)->whereNull('parent_id')->get();
echo "Root folders: {$folders->count()}\n";
foreach ($folders as $folder) {
    $pageCount = $folder->pages()->count();
    echo "  - {$folder->name} ({$folder->slug}): {$pageCount} pages\n";
}
echo "\n";

// Simulate featured logic
echo "=== Featured Pages Logic ===\n";
$featuredPages = [];
foreach ($folders as $folder) {
    $folderPages = $folder->pages()->limit(3)->get();
    echo "Folder '{$folder->name}': getting " . $folderPages->count() . " pages\n";
    foreach ($folderPages as $page) {
        $featuredPages[] = $page;
        echo "  + {$page->title} (ID: {$page->id})\n";
        if (count($featuredPages) >= 8) {
            echo "  (Reached max 8, breaking)\n";
            break 2;
        }
    }
}
echo "Total featured pages collected: " . count($featuredPages) . "\n\n";

// Simulate newest logic
echo "=== Newest Pages Logic ===\n";
$newestPages = [];
foreach ($folders as $folder) {
    $folderPages = $folder->pages()->orderBy('updated_at', 'desc')->limit(3)->get();
    echo "Folder '{$folder->name}': getting " . $folderPages->count() . " pages\n";
    foreach ($folderPages as $page) {
        $newestPages[] = $page;
        echo "  + {$page->title} (updated: {$page->updated_at})\n";
    }
}
$newestPages = collect($newestPages)->sortByDesc('updated_at')->take(8);
echo "Total newest pages after sorting: " . $newestPages->count() . "\n\n";

echo "=== Conclusion ===\n";
echo "Featured will show: " . min(count($featuredPages), 8) . " items\n";
echo "Newest will show: " . min($newestPages->count(), 8) . " items\n";
