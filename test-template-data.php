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
echo "Page ID: {$page->id}\n\n";

echo "=== template_data ===\n";
$data = $page->template_data ?? [];
if (empty($data)) {
    echo "template_data is EMPTY! This is why renderPageContent returns early.\n";
} else {
    echo "template_data has " . count($data) . " keys\n";
    echo "Keys: " . implode(', ', array_keys($data)) . "\n\n";

    if (isset($data['breadcrumb_items'])) {
        echo "breadcrumb_items: " . json_encode($data['breadcrumb_items']) . "\n";
    }
}
