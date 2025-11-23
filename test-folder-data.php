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
echo "Primary folder ID: " . ($page->primary_folder_id ?? 'NULL') . "\n";
echo "Folders count: " . $page->folders()->count() . "\n\n";

// Test logic from renderPageContent
$folder = null;
if ($page->primary_folder_id) {
    $folder = $page->primaryFolder;
    echo "Using primary folder\n";
} else {
    $folder = $page->folders()->first();
    echo "Using first folder\n";
}

if ($folder) {
    echo "Folder found: {$folder->name}\n";
    echo "Folder path: {$folder->getPath()}\n";

    $data = [];
    $data['primary_folder_name'] = $folder->name;
    $data['primary_folder_path'] = $folder->getPath();

    // Test main domain URL
    $domainParts = explode('.', $website->domain);
    $mainDomain = count($domainParts) > 2 ? implode('.', array_slice($domainParts, -2)) : $website->domain;
    $protocol = $website->ssl_enabled ? 'https://' : 'http://';
    $data['main_domain_url'] = $protocol . $mainDomain;

    echo "\n=== Data that should be in page-data ===\n";
    echo "main_domain_url: {$data['main_domain_url']}\n";
    echo "primary_folder_path: {$data['primary_folder_path']}\n";
    echo "primary_folder_name: {$data['primary_folder_name']}\n";
} else {
    echo "No folder found!\n";
    echo "\nDEBUG: Checking folders relationship...\n";
    $folders = $page->folders;
    echo "Folders via property: " . $folders->count() . "\n";
    foreach ($folders as $f) {
        echo "  - {$f->name}\n";
    }
}
