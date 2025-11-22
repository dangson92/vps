#!/bin/bash
# Fix template assets 404 error

echo "=== Fixing Template Assets 404 Error ==="
echo ""

echo "Step 1: Deploying template assets..."
./deploy-template-assets.php

echo ""
echo "Step 2: Checking deployed files..."

# Find main domain
DOMAIN=$(mysql -u vps_manager -pa22f3fda13ee00562544c950e33aceb2 vps_manager -sN -e "SELECT domain FROM websites WHERE type='laravel1' AND status='deployed' AND domain NOT LIKE '%.%.%' LIMIT 1;" 2>/dev/null)

if [ -z "$DOMAIN" ]; then
    echo "❌ No main domain found"
    exit 1
fi

DOC_ROOT="/var/www/$DOMAIN"

echo "Checking: $DOC_ROOT/templates/"

if [ -d "$DOC_ROOT/templates/home-1" ]; then
    echo "✅ home-1 folder exists"
    ls -lh "$DOC_ROOT/templates/home-1/"
else
    echo "❌ home-1 folder NOT found"
fi

echo ""

if [ -d "$DOC_ROOT/templates/listing-1" ]; then
    echo "✅ listing-1 folder exists"
    ls -lh "$DOC_ROOT/templates/listing-1/"
else
    echo "❌ listing-1 folder NOT found"
fi

echo ""

if [ -d "$DOC_ROOT/templates/hotel-detail-1" ]; then
    echo "✅ hotel-detail-1 folder exists"
    ls -lh "$DOC_ROOT/templates/hotel-detail-1/"
else
    echo "❌ hotel-detail-1 folder NOT found"
fi

echo ""
echo "=== Done ==="
echo "Now check homepage in browser: https://$DOMAIN"
