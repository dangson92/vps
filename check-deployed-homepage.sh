#!/bin/bash
# Check deployed homepage content

echo "=== Checking Deployed Homepage ==="
echo ""

# Find main domain website ID from database
WEBSITE_ID=$(mysql -u vps_manager -pa22f3fda13ee00562544c950e33aceb2 vps_manager -sN -e "SELECT id FROM websites WHERE type='laravel1' AND status='deployed' AND domain NOT LIKE '%.%.%' LIMIT 1;" 2>/dev/null)

if [ -z "$WEBSITE_ID" ]; then
    echo "❌ No main domain laravel1 website found"
    exit 1
fi

DOMAIN=$(mysql -u vps_manager -pa22f3fda13ee00562544c950e33aceb2 vps_manager -sN -e "SELECT domain FROM websites WHERE id=$WEBSITE_ID;" 2>/dev/null)
DOC_ROOT=$(mysql -u vps_manager -pa22f3fda13ee00562544c950e33aceb2 vps_manager -sN -e "SELECT document_root FROM websites WHERE id=$WEBSITE_ID;" 2>/dev/null)

# If document_root is NULL, calculate it
if [ -z "$DOC_ROOT" ]; then
    DOC_ROOT="/var/www/$DOMAIN"
fi

echo "Website ID: $WEBSITE_ID"
echo "Domain: $DOMAIN"
echo "Document Root: $DOC_ROOT"
echo ""

INDEX_FILE="$DOC_ROOT/index.html"

if [ ! -f "$INDEX_FILE" ]; then
    echo "❌ Homepage not found at: $INDEX_FILE"
    exit 1
fi

echo "✅ Homepage file exists"
echo ""

# Check for page-data script
if grep -q "id=\"page-data\"" "$INDEX_FILE"; then
    echo "✅ page-data script tag found"
    echo ""
    echo "=== Page Data Content ==="
    grep -A 1 "id=\"page-data\"" "$INDEX_FILE" | tail -n 1 | python3 -m json.tool 2>/dev/null || grep -A 1 "id=\"page-data\"" "$INDEX_FILE" | tail -n 1
    echo ""
else
    echo "❌ page-data script tag NOT found"
    echo ""
    echo "Checking for placeholder:"
    if grep -q "{{PAGE_DATA_SCRIPT}}" "$INDEX_FILE"; then
        echo "⚠️  Found {{PAGE_DATA_SCRIPT}} placeholder - template not rendered!"
    fi
fi

# Check sections
echo "=== Template Sections ==="
grep -c "id=\"categories-grid\"" "$INDEX_FILE" && echo "✅ Categories section exists" || echo "❌ Categories section missing"
grep -c "id=\"featured-grid\"" "$INDEX_FILE" && echo "✅ Featured section exists" || echo "❌ Featured section missing"
grep -c "id=\"newest-grid\"" "$INDEX_FILE" && echo "✅ Newest section exists" || echo "❌ Newest section missing"

echo ""
echo "File size: $(du -h "$INDEX_FILE" | cut -f1)"
echo "Last modified: $(stat -c %y "$INDEX_FILE")"
