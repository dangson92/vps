#!/usr/bin/php
<?php

/**
 * Sync shared header/footer to all templates
 *
 * This script updates header and footer in all template HTML files
 * to match the shared components in _shared/ directory.
 */

$sharedDir = __DIR__ . '/public/templates/_shared';
$templatesDir = __DIR__ . '/public/templates';

// Read shared components
$sharedHeader = file_get_contents($sharedDir . '/header.html');
$sharedFooter = file_get_contents($sharedDir . '/footer.html');

if (!$sharedHeader || !$sharedFooter) {
    echo "ERROR: Could not read shared header or footer\n";
    exit(1);
}

echo "=== Syncing Template Components ===\n\n";
echo "Shared header: " . strlen($sharedHeader) . " bytes\n";
echo "Shared footer: " . strlen($sharedFooter) . " bytes\n\n";

// Find all template directories (exclude _shared)
$templates = array_filter(glob($templatesDir . '/*'), function($path) {
    return is_dir($path) && basename($path) !== '_shared';
});

$updatedCount = 0;
$skippedCount = 0;

foreach ($templates as $templateDir) {
    $templateName = basename($templateDir);
    $indexFile = $templateDir . '/index.html';

    if (!file_exists($indexFile)) {
        echo "⊘ {$templateName}: No index.html found\n";
        $skippedCount++;
        continue;
    }

    $content = file_get_contents($indexFile);
    $originalContent = $content;
    $updated = false;

    // Update header (between <header> and </header>)
    $headerPattern = '/<header[^>]*>.*?<\/header>/s';
    if (preg_match($headerPattern, $content)) {
        $content = preg_replace($headerPattern, $sharedHeader, $content);
        $updated = true;
        echo "✓ {$templateName}: Updated header\n";
    } else {
        echo "⊘ {$templateName}: No header found\n";
    }

    // Update footer (between <footer> and </footer>, including <!-- Footer --> comment)
    $footerPattern = '/(?:<!--\s*Footer\s*-->\\s*)?<footer[^>]*>.*?<\/footer>/s';
    if (preg_match($footerPattern, $content)) {
        $content = preg_replace($footerPattern, $sharedFooter, $content);
        $updated = true;
        echo "✓ {$templateName}: Updated footer\n";
    } else {
        echo "⊘ {$templateName}: No footer found\n";
    }

    if ($updated && $content !== $originalContent) {
        file_put_contents($indexFile, $content);
        $updatedCount++;
    } else {
        $skippedCount++;
    }

    echo "\n";
}

echo "=== Summary ===\n";
echo "Updated: {$updatedCount} templates\n";
echo "Skipped: {$skippedCount} templates\n";
echo "\n=== Done ===\n";
echo "\nNOTE: Remember to deploy updated templates to the server:\n";
echo "  ./redeploy-template-assets.php <template-name>\n";
