#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Website;
use App\Models\Page;

echo "=== Test Page Save & Queue Dispatch ===\n\n";

// Find a test page
$website = Website::where('type', 'laravel1')
    ->where('status', 'deployed')
    ->whereRaw('LENGTH(domain) - LENGTH(REPLACE(domain, ".", "")) = 1') // Main domain only
    ->first();

if (!$website) {
    echo "ERROR: No main domain Laravel1 website found\n";
    exit(1);
}

echo "Website: {$website->domain} (ID: {$website->id})\n";

$page = Page::where('website_id', $website->id)->first();

if (!$page) {
    echo "ERROR: No page found for this website\n";
    exit(1);
}

echo "Page: {$page->title} (ID: {$page->id})\n\n";

// Check jobs before
$jobsBefore = DB::table('jobs')->count();
echo "Jobs in queue BEFORE: {$jobsBefore}\n";

// Simulate page update (trigger redeployment)
echo "Updating page title...\n";
$oldTitle = $page->title;
$page->title = $page->title . ' (test)';
$page->save();

echo "✓ Page saved\n";

// The PageController would call redeployLaravel1IfNeeded here
// But when updating via model directly, we need to trigger it manually
echo "\nManually triggering deployment...\n";

// Get the folders this page belongs to
$folders = $page->folders;
echo "Page belongs to {$folders->count()} folders\n";

// Dispatch homepage job
\App\Jobs\DeployLaravel1Homepage::dispatch($website->id);
echo "✓ Homepage job dispatched\n";

// Dispatch category jobs
foreach ($folders as $folder) {
    \App\Jobs\DeployLaravel1CategoryPage::dispatch($folder->id);
    echo "✓ Category '{$folder->name}' job dispatched\n";
}

// Wait a moment and check
sleep(1);

$jobsAfter = DB::table('jobs')->count();
echo "\nJobs in queue AFTER: {$jobsAfter}\n";
echo "New jobs added: " . ($jobsAfter - $jobsBefore) . "\n\n";

if ($jobsAfter > $jobsBefore) {
    echo "✓ SUCCESS: Jobs were added to queue!\n";
    echo "\nTo process them:\n";
    echo "  php artisan queue:work --once\n";
    echo "Or check worker logs:\n";
    echo "  sudo journalctl -u vps-queue-worker -f\n";
} else {
    echo "✗ PROBLEM: No jobs added to queue\n";
    echo "\nPossible causes:\n";
    echo "  1. Jobs were processed immediately (check QUEUE_CONNECTION in .env)\n";
    echo "  2. Jobs failed to dispatch (check Laravel logs)\n";
    echo "  3. Jobs were processed so fast they're already done\n";
}

// Restore original title
$page->title = $oldTitle;
$page->save();
echo "\n✓ Page title restored\n";

echo "\n=== Test Complete ===\n";
