#!/usr/bin/php
<?php

/**
 * Update page HTML content from updated template
 *
 * This script updates the HTML content of existing pages to use
 * the latest template HTML (useful when template header/footer changes)
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Website;
use App\Models\Page;
use App\Services\DeploymentService;

if ($argc < 2) {
    echo "Usage: php update-pages-template.php <domain> [template-name]\n";
    echo "Example: php update-pages-template.php timnhakhoa.com\n";
    echo "Example: php update-pages-template.php timnhakhoa.com hotel-detail-1\n";
    exit(1);
}

$domain = $argv[1];
$templateFilter = $argv[2] ?? null;

echo "=== Update Pages with New Template HTML ===\n\n";

// Get main domain and subdomains
$domainPattern = "%." . $domain;
$websites = Website::where(function($q) use ($domain, $domainPattern) {
    $q->where('domain', $domain)->orWhere('domain', 'like', $domainPattern);
})->where('type', 'laravel1')->where('status', 'deployed')->get();

echo "Found {$websites->count()} websites\n\n";

$updatedCount = 0;
$skippedCount = 0;

foreach ($websites as $website) {
    $pages = Page::where('website_id', $website->id)->get();

    foreach ($pages as $page) {
        // Get template name from page
        preg_match('/href="\/templates\/([^\/]+)\//', $page->content, $matches);
        $templateName = $matches[1] ?? null;

        if (!$templateName) {
            echo "⊘ {$website->domain}{$page->path}: No template detected\n";
            $skippedCount++;
            continue;
        }

        // Filter by template if specified
        if ($templateFilter && $templateName !== $templateFilter) {
            continue;
        }

        // Read template file
        $templateFile = public_path("templates/{$templateName}/index.html");
        if (!file_exists($templateFile)) {
            echo "⊘ {$website->domain}{$page->path}: Template file not found\n";
            $skippedCount++;
            continue;
        }

        $templateHtml = file_get_contents($templateFile);
        $currentHtml = $page->content;
        $updated = false;

        // Update only header (not entire HTML to avoid breaking rendered content)
        $headerPattern = '/<header[^>]*>.*?<\/header>/s';
        if (preg_match($headerPattern, $templateHtml, $templateHeaderMatch) &&
            preg_match($headerPattern, $currentHtml)) {
            $newHeader = $templateHeaderMatch[0];
            $currentHtml = preg_replace($headerPattern, $newHeader, $currentHtml);
            $updated = true;
            echo "✓ {$website->domain}{$page->path}: Updated header\n";
        }

        // Update only footer
        $footerPattern = '/(?:<!--\s*Footer\s*-->\\s*)?<footer[^>]*>.*?<\/footer>/s';
        if (preg_match($footerPattern, $templateHtml, $templateFooterMatch) &&
            preg_match($footerPattern, $currentHtml)) {
            $newFooter = $templateFooterMatch[0];
            $currentHtml = preg_replace($footerPattern, $newFooter, $currentHtml);
            $updated = true;
            echo "✓ {$website->domain}{$page->path}: Updated footer\n";
        }

        if ($updated) {
            $page->content = $currentHtml;
            $page->save();
            $updatedCount++;
        } else {
            echo "⊘ {$website->domain}{$page->path}: No updates needed\n";
            $skippedCount++;
        }
    }
}

echo "\n=== Summary ===\n";
echo "Updated: {$updatedCount} pages\n";
echo "Skipped: {$skippedCount} pages\n";
echo "\n=== Done ===\n";
echo "\nNext step: Redeploy pages to push updated HTML to server\n";
echo "  ./redeploy-pages.php {$domain}\n";
