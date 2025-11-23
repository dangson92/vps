#!/usr/bin/php
<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Website;
use App\Models\Page;

$subdomain = 'luxeden-hanoi-hotel.timnhakhoa.com';

$website = Website::where('domain', $subdomain)->first();
if (!$website) {
    echo "Website not found\n";
    exit(1);
}

$page = Page::where('website_id', $website->id)->first();
if (!$page) {
    echo "No pages found\n";
    exit(1);
}

echo "Page: {$page->title}\n";
echo "Primary folder ID: " . ($page->primary_folder_id ?? 'NULL') . "\n\n";

echo "=== All Folders This Page Belongs To ===\n";
$folders = $page->folders;
foreach ($folders as $folder) {
    $parent = $folder->parent;
    $parentName = $parent ? $parent->name : 'NULL';
    echo "- {$folder->name} (ID: {$folder->id}, Parent: {$parentName}, Path: {$folder->getPath()})\n";
}

echo "\n=== First Folder (What Code Currently Uses) ===\n";
$firstFolder = $page->folders()->first();
if ($firstFolder) {
    echo "Name: {$firstFolder->name}\n";
    echo "Path: {$firstFolder->getPath()}\n";
    echo "Parent: " . ($firstFolder->parent ? $firstFolder->parent->name : 'NULL') . "\n";
} else {
    echo "No folder found!\n";
}

echo "\n=== Recommendation ===\n";
// Find the deepest folder (one with a parent)
$deepestFolder = null;
foreach ($folders as $folder) {
    if ($folder->parent) {
        $deepestFolder = $folder;
        break;
    }
}

if ($deepestFolder && $deepestFolder->id !== $firstFolder->id) {
    echo "⚠️  Page should use '{$deepestFolder->name}' instead of '{$firstFolder->name}'\n";
    echo "   Set primary_folder_id = {$deepestFolder->id} for this page\n";
    echo "   Or update the logic to use the deepest folder automatically\n";
} else {
    echo "✓ Current folder selection is correct\n";
}
