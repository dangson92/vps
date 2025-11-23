#!/usr/bin/php
<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Website;
use App\Models\Page;

$subdomain = 'luxeden-hanoi-hotel.timnhakhoa.com';

echo "=== Checking Page Data After Deployment ===\n\n";

$website = Website::where('domain', $subdomain)->first();
if (!$website) {
    echo "ERROR: Website not found\n";
    exit(1);
}

$page = Page::where('website_id', $website->id)->first();
if (!$page) {
    echo "ERROR: No pages found\n";
    exit(1);
}

echo "Page: {$page->title}\n";
echo "Website: {$website->domain}\n\n";

// Simulate what renderPageContent() will generate
$data = $page->template_data ?? [];

if (empty($data)) {
    echo "ERROR: template_data is empty!\n";
    exit(1);
}

// Get folder
$folder = null;
if ($page->primary_folder_id) {
    $folder = $page->primaryFolder;
} else {
    $folder = $page->folders()->first();
}

if (!$folder) {
    echo "ERROR: No folder found for this page!\n";
    exit(1);
}

echo "Folder: {$folder->name}\n";
echo "Folder path: {$folder->getPath()}\n\n";

// Generate breadcrumb like the code does
$breadcrumbItems = ['Home'];
$breadcrumbPaths = [''];

// Get all parent folders in order
$folderHierarchy = [];
$currentFolder = $folder;
while ($currentFolder) {
    array_unshift($folderHierarchy, $currentFolder);
    $currentFolder = $currentFolder->parent;
}

echo "Folder hierarchy:\n";
foreach ($folderHierarchy as $f) {
    echo "  - {$f->name} → {$f->getPath()}\n";
}
echo "\n";

// Add folder names and paths to breadcrumb
foreach ($folderHierarchy as $f) {
    $breadcrumbItems[] = $f->name;
    $breadcrumbPaths[] = $f->getPath();
}

// Add page title
$breadcrumbItems[] = $data['title'] ?? $page->title ?? 'Untitled';
$breadcrumbPaths[] = '';

echo "=== Expected Breadcrumb Data ===\n";
echo "breadcrumb_items: " . json_encode($breadcrumbItems) . "\n";
echo "breadcrumb_paths: " . json_encode($breadcrumbPaths) . "\n\n";

// Get main domain URL
$domainParts = explode('.', $website->domain);
$mainDomain = count($domainParts) > 2 ? implode('.', array_slice($domainParts, -2)) : $website->domain;
$protocol = $website->ssl_enabled ? 'https://' : 'http://';
$mainDomainUrl = $protocol . $mainDomain;

echo "main_domain_url: {$mainDomainUrl}\n\n";

echo "=== Expected Breadcrumb Display ===\n";
for ($i = 0; $i < count($breadcrumbItems); $i++) {
    $item = $breadcrumbItems[$i];
    $path = $breadcrumbPaths[$i];
    $isLast = $i === count($breadcrumbItems) - 1;

    if ($i > 0) echo " / ";

    if ($isLast) {
        echo $item . " (no link)";
    } else if ($path) {
        echo $item . " → " . $mainDomainUrl . $path;
    } else {
        echo $item . " → " . $mainDomainUrl;
    }
}
echo "\n";
