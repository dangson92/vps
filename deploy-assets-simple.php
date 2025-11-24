#!/usr/bin/php
<?php

// Simple template assets deployment without database dependency
// Just specify domain directly

if ($argc < 2) {
    echo "Usage: php deploy-assets-simple.php <domain> [vps_server_id] [worker_key]\n";
    echo "Example: php deploy-assets-simple.php yourdomain.com 1 112435988\n";
    exit(1);
}

$domain = $argv[1];
$vpsServerId = $argv[2] ?? 1;
$workerKey = $argv[3] ?? '112435988';

echo "=== Deploy Template Assets (Simple) ===\n\n";
echo "Domain: $domain\n";
echo "VPS Server ID: $vpsServerId\n\n";

// Get VPS server IP from .env or use localhost
$vpsIp = '127.0.0.1';

$documentRoot = "/var/www/$domain";
$templatesPath = __DIR__ . "/public/templates";

if (!is_dir($templatesPath)) {
    echo "❌ Templates directory not found: $templatesPath\n";
    exit(1);
}

$templates = ['home-1', 'listing-1', 'hotel-detail-1'];
$deployed = 0;
$failed = 0;

foreach ($templates as $template) {
    echo "Deploying $template assets...\n";

    $templateDir = "$templatesPath/$template";
    if (!is_dir($templateDir)) {
        echo "  ⚠️  Template directory not found: $templateDir\n";
        continue;
    }

    $files = ['style.css', 'script.js'];

    foreach ($files as $file) {
        $filePath = "$templateDir/$file";

        if (!file_exists($filePath)) {
            echo "  ⚠️  File not found: $file\n";
            continue;
        }

        $content = file_get_contents($filePath);

        $payload = [
            'website_id' => 0, // Not needed for assets
            'page_path' => "/templates/$template",
            'filename' => $file,
            'content' => $content,
            'document_root' => $documentRoot,
        ];

        $ch = curl_init("http://$vpsIp:8080/api/deploy-page");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Worker-Key: ' . $workerKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            echo "  ✓ $file deployed\n";
            $deployed++;
        } else {
            echo "  ✗ Failed to deploy $file (HTTP $httpCode)\n";
            if ($response) echo "    Response: $response\n";
            $failed++;
        }
    }

    echo "\n";
}

echo "=== Summary ===\n";
echo "Deployed: $deployed files\n";
echo "Failed: $failed files\n\n";

// Check deployed files
echo "=== Checking Deployed Files ===\n";
foreach ($templates as $template) {
    $dir = "$documentRoot/templates/$template";
    if (is_dir($dir)) {
        echo "✅ $template:\n";
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $size = filesize("$dir/$file");
            echo "  - $file (" . number_format($size) . " bytes)\n";
        }
    } else {
        echo "❌ $template: Directory not found\n";
    }
}

echo "\n=== Done ===\n";
echo "Check your website: https://$domain\n";
