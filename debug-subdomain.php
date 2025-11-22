#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Website;
use App\Models\Page;

if ($argc < 2) {
    echo "Usage: php debug-subdomain.php <subdomain>\n";
    echo "Example: php debug-subdomain.php luxeden-hanoi-hotel.timnhakhoa.com\n";
    exit(1);
}

$domain = $argv[1];

echo "=== Subdomain Debug ===\n\n";

$website = Website::where('domain', $domain)->first();

if (!$website) {
    echo "ERROR: Website '{$domain}' not found\n";
    exit(1);
}

echo "Website: {$website->domain}\n";
echo "ID: {$website->id}\n";
echo "Type: {$website->type}\n";
echo "Status: {$website->status}\n";
echo "Document Root: {$website->getDocumentRoot()}\n\n";

// Find page
$page = Page::where('website_id', $website->id)->first();

if (!$page) {
    echo "ERROR: No page found for this website\n";
    exit(1);
}

echo "Page:\n";
echo "  ID: {$page->id}\n";
echo "  Title: {$page->title}\n";
echo "  Path: {$page->path}\n";
echo "  Template: {$page->template_type}\n\n";

// Check template data
echo "Template Data:\n";
if ($page->template_data) {
    $data = $page->template_data;
    echo "  Title: " . ($data['title'] ?? 'N/A') . "\n";
    echo "  About: " . substr($data['about1'] ?? 'N/A', 0, 100) . "...\n";
    echo "  Location: " . ($data['location_text'] ?? $data['location'] ?? 'N/A') . "\n";
    echo "  Gallery count: " . count($data['gallery'] ?? []) . "\n";
    echo "  Amenities count: " . count($data['amenities'] ?? []) . "\n";
} else {
    echo "  No template data\n";
}

echo "\n";

// Check if file exists on server
$documentRoot = $website->getDocumentRoot();
$filePath = $documentRoot . $page->path . '/' . $page->filename;

echo "Expected file location: {$filePath}\n";
echo "Note: Check this file on the server to verify content\n\n";

// Get folders this page belongs to
$folders = $page->folders;
echo "Folders: " . $folders->count() . "\n";
foreach ($folders as $folder) {
    echo "  - {$folder->name} (slug: {$folder->slug})\n";
}

echo "\n=== Debug Complete ===\n";
