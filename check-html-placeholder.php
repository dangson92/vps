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

echo "Page: {$page->title}\n\n";

echo "=== Checking HTML for data injection ===\n";

$html = $page->content;

if (strpos($html, '{{PAGE_DATA_SCRIPT}}') !== false) {
    echo "✓ Found {{PAGE_DATA_SCRIPT}} placeholder\n";
    echo "  This means renderPageContent() should inject data correctly.\n\n";
} else {
    echo "✗ {{PAGE_DATA_SCRIPT}} placeholder NOT FOUND\n";
    echo "  Checking if script tag already exists...\n\n";

    if (strpos($html, 'id="page-data"') !== false) {
        echo "✓ Found <script id=\"page-data\"> in HTML (hardcoded)\n";
        echo "  Problem: Data is hardcoded in HTML, not injected dynamically!\n\n";

        // Extract the hardcoded data
        if (preg_match('/<script[^>]*id="page-data"[^>]*>(.*?)<\/script>/s', $html, $matches)) {
            $jsonData = $matches[1];
            $data = json_decode($jsonData, true);
            echo "  Hardcoded data keys: " . implode(', ', array_keys($data)) . "\n";

            if (isset($data['main_domain_url'])) {
                echo "  main_domain_url in hardcoded data: {$data['main_domain_url']}\n";
            } else {
                echo "  main_domain_url: NOT IN HARDCODED DATA\n";
            }
        }
    } else {
        echo "✗ No script tag with id=\"page-data\" found\n";
        echo "  Problem: No way to inject page data!\n";
    }
}
