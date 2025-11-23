#!/usr/bin/php
<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Website;
use App\Models\Folder;

if ($argc < 2) {
    echo "Usage: php dump-homepage-html.php <domain>\n";
    exit(1);
}

$domain = $argv[1];

$website = Website::where('domain', $domain)->first();
if (!$website) {
    echo "ERROR: Website not found\n";
    exit(1);
}

echo "=== Generating Homepage HTML ===\n\n";

$folders = Folder::where('website_id', $website->id)->whereNull('parent_id')->get();

echo "Folders: {$folders->count()}\n";
foreach ($folders as $folder) {
    echo "  - {$folder->name}: " . $folder->pages()->count() . " pages\n";
}
echo "\n";

// Get featured pages
$featuredPages = [];
foreach ($folders as $folder) {
    $folderPages = $folder->pages()->limit(3)->get();
    foreach ($folderPages as $page) {
        $featuredPages[] = $page;
        if (count($featuredPages) >= 8) break 2;
    }
}

echo "Featured pages collected: " . count($featuredPages) . "\n";
foreach ($featuredPages as $page) {
    echo "  - {$page->title}\n";
}
echo "\n";

// Get newest pages
$newestPages = [];
foreach ($folders as $folder) {
    $folderPages = $folder->pages()->orderBy('updated_at', 'desc')->limit(3)->get();
    foreach ($folderPages as $page) {
        $newestPages[] = $page;
    }
}
$newestPages = collect($newestPages)->sortByDesc('updated_at')->take(8);

echo "Newest pages collected: " . $newestPages->count() . "\n";
foreach ($newestPages as $page) {
    echo "  - {$page->title}\n";
}
echo "\n";

// Generate data arrays
$featuredData = collect($featuredPages)->map(function ($page) use ($website) {
    $data = $page->template_data ?? [];
    $gallery = $data['gallery'] ?? [];

    // Generate URL
    $pageWebsite = $page->website;
    if ($pageWebsite->id !== $website->id) {
        $protocol = $pageWebsite->ssl_enabled ? 'https://' : 'http://';
        $url = $protocol . $pageWebsite->domain . $page->path;
    } else {
        $url = $page->path;
    }

    return [
        'title' => $data['title'] ?? $page->title ?? 'Untitled',
        'image' => $gallery[0] ?? '',
        'location_text' => $data['location_text'] ?? $data['location'] ?? '',
        'url' => $url,
    ];
})->toArray();

$newestData = $newestPages->map(function ($page) use ($website) {
    $data = $page->template_data ?? [];
    $gallery = $data['gallery'] ?? [];

    // Generate URL
    $pageWebsite = $page->website;
    if ($pageWebsite->id !== $website->id) {
        $protocol = $pageWebsite->ssl_enabled ? 'https://' : 'http://';
        $url = $protocol . $pageWebsite->domain . $page->path;
    } else {
        $url = $page->path;
    }

    return [
        'title' => $data['title'] ?? $page->title ?? 'Untitled',
        'image' => $gallery[0] ?? '',
        'location_text' => $data['location_text'] ?? $data['location'] ?? '',
        'url' => $url,
    ];
})->values()->toArray();

echo "=== JSON Data ===\n\n";
echo "Featured array count: " . count($featuredData) . "\n";
echo json_encode($featuredData, JSON_PRETTY_PRINT) . "\n\n";

echo "Newest array count: " . count($newestData) . "\n";
echo json_encode($newestData, JSON_PRETTY_PRINT) . "\n\n";

echo "=== Page Data Script ===\n";
$pageData = [
    'categories' => [],
    'featured' => $featuredData,
    'newest' => $newestData,
];
echo '<script type="application/json" id="page-data">' . json_encode($pageData) . '</script>' . "\n";
