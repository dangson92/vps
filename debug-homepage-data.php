#!/usr/bin/env php
<?php

// Debug script to check homepage data
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Website;
use App\Models\Folder;

echo "=== Homepage Data Debug ===\n\n";

// Find laravel1 main domain
$websites = Website::where('type', 'laravel1')
    ->where('status', 'deployed')
    ->get();

foreach ($websites as $website) {
    $domainParts = explode('.', $website->domain);
    $isSubdomain = count($domainParts) > 2;

    if ($isSubdomain) continue; // Skip subdomains

    echo "Website: {$website->domain} (ID: {$website->id})\n";
    echo str_repeat("=", 60) . "\n\n";

    // Check folders
    $folders = Folder::where('website_id', $website->id)->whereNull('parent_id')->get();
    echo "📁 Folders: " . $folders->count() . "\n";

    foreach ($folders as $folder) {
        $pageCount = $folder->pages()->count();
        $firstPage = $folder->pages()->first();

        echo "  - {$folder->name} (/{$folder->slug})\n";
        echo "    Pages: {$pageCount}\n";

        if ($firstPage) {
            $data = $firstPage->template_data ?? [];
            $gallery = $data['gallery'] ?? [];
            echo "    First page: {$firstPage->title}\n";
            echo "    Gallery images: " . count($gallery) . "\n";
            echo "    Template data: " . (empty($data) ? "EMPTY" : "OK") . "\n";
        } else {
            echo "    ⚠️  No pages in this folder\n";
        }
        echo "\n";
    }

    // Check featured pages
    echo "\n⭐ Featured Pages Data:\n";
    $featuredPages = [];
    foreach ($folders as $folder) {
        $folderPages = $folder->pages()->limit(2)->get();
        foreach ($folderPages as $page) {
            $featuredPages[] = $page;
            if (count($featuredPages) >= 6) break 2;
        }
    }

    echo "Total featured pages: " . count($featuredPages) . "\n";
    foreach ($featuredPages as $page) {
        $data = $page->template_data ?? [];
        $gallery = $data['gallery'] ?? [];
        echo "  - {$page->title} ({$page->path})\n";
        echo "    Gallery: " . count($gallery) . " images\n";
        echo "    Title: " . ($data['title'] ?? 'MISSING') . "\n";
        echo "    Location: " . ($data['location_text'] ?? $data['location'] ?? 'MISSING') . "\n";
    }

    // Check newest pages
    echo "\n🆕 Newest Pages Data:\n";
    $newestPages = [];
    foreach ($folders as $folder) {
        $folderPages = $folder->pages()->orderBy('updated_at', 'desc')->limit(2)->get();
        foreach ($folderPages as $page) {
            $newestPages[] = $page;
        }
    }

    $newestPages = collect($newestPages)->sortByDesc('updated_at')->take(6);
    echo "Total newest pages: " . $newestPages->count() . "\n";
    foreach ($newestPages as $page) {
        $data = $page->template_data ?? [];
        $gallery = $data['gallery'] ?? [];
        echo "  - {$page->title} (Updated: {$page->updated_at})\n";
        echo "    Gallery: " . count($gallery) . " images\n";
        echo "    Path: {$page->path}\n";
    }

    // Check categories data
    echo "\n📂 Categories Data:\n";
    $categoriesData = $folders->map(function ($folder) use ($website) {
        $pageCount = $folder->pages()->count();
        $firstPage = $folder->pages()->first();
        $firstPageData = $firstPage ? ($firstPage->template_data ?? []) : [];
        $gallery = $firstPageData['gallery'] ?? [];
        return [
            'name' => $folder->name,
            'url' => '/' . $folder->slug,
            'count' => $pageCount,
            'image' => $gallery[0] ?? 'DEFAULT',
        ];
    })->toArray();

    echo json_encode($categoriesData, JSON_PRETTY_PRINT) . "\n\n";

    // Summary
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "SUMMARY:\n";
    echo "  Total folders: " . $folders->count() . "\n";
    echo "  Total pages across all folders: ";
    $totalPages = 0;
    foreach ($folders as $folder) {
        $totalPages += $folder->pages()->count();
    }
    echo $totalPages . "\n";
    echo "  Featured pages ready: " . count($featuredPages) . " / 6\n";
    echo "  Newest pages ready: " . $newestPages->count() . " / 6\n";

    if ($totalPages == 0) {
        echo "\n⚠️  WARNING: No pages found! Please add pages to folders.\n";
    }
    if (count($featuredPages) == 0) {
        echo "\n⚠️  WARNING: No featured pages! Homepage will be empty.\n";
    }

    echo "\n";
}

echo "=== Debug Complete ===\n";
