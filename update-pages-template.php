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

        // Keep the page data script from current page
        if (preg_match('/<script[^>]*id="page-data"[^>]*>.*?<\/script>/s', $page->content, $dataMatches)) {
            $pageDataScript = $dataMatches[0];

            // Replace {{PAGE_DATA_SCRIPT}} in template with actual data
            $templateHtml = str_replace('{{PAGE_DATA_SCRIPT}}', $pageDataScript, $templateHtml);
        }

        // Keep SEO meta tags
        preg_match('/<title>(.*?)<\/title>/s', $page->content, $titleMatches);
        $title = $titleMatches[1] ?? '{{TITLE}}';
        $templateHtml = preg_replace('/<title>.*?<\/title>/s', "<title>{$title}</title>", $templateHtml);

        // Update page content
        $page->content = $templateHtml;
        $page->save();

        echo "✓ {$website->domain}{$page->path}: Updated with {$templateName} template\n";
        $updatedCount++;
    }
}

echo "\n=== Summary ===\n";
echo "Updated: {$updatedCount} pages\n";
echo "Skipped: {$skippedCount} pages\n";
echo "\n=== Done ===\n";
echo "\nNext step: Redeploy pages to push updated HTML to server\n";
echo "  ./redeploy-pages.php {$domain}\n";
