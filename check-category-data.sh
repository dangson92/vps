#!/bin/bash
# Check deployed category page data

if [ -z "$1" ]; then
    echo "Usage: ./check-category-data.sh <domain> <category_slug>"
    echo "Example: ./check-category-data.sh timnhakhoa.com vietnam"
    exit 1
fi

DOMAIN=$1
CATEGORY=${2:-}

DOC_ROOT="/var/www/$DOMAIN"

echo "=== Checking Category Page Data ==="
echo "Domain: $DOMAIN"
echo "Document Root: $DOC_ROOT"
echo ""

if [ -z "$CATEGORY" ]; then
    echo "Available categories:"
    ls -d $DOC_ROOT/*/ 2>/dev/null | grep -v "templates" | xargs -n 1 basename || echo "No categories found"
    exit 0
fi

CATEGORY_FILE="$DOC_ROOT/$CATEGORY/index.html"

if [ ! -f "$CATEGORY_FILE" ]; then
    echo "❌ Category file not found: $CATEGORY_FILE"
    exit 1
fi

echo "✅ Category file exists: $CATEGORY_FILE"
echo "Size: $(du -h "$CATEGORY_FILE" | cut -f1)"
echo "Last modified: $(stat -c %y "$CATEGORY_FILE")"
echo ""

# Extract page-data JSON
echo "=== Page Data ==="
if grep -q "id=\"page-data\"" "$CATEGORY_FILE"; then
    echo "✅ page-data found"
    echo ""
    JSON=$(grep -A 1 "id=\"page-data\"" "$CATEGORY_FILE" | tail -n 1)

    # Try to pretty print or show raw
    echo "$JSON" | python3 -m json.tool 2>/dev/null || echo "$JSON"

    echo ""
    echo "=== URL Analysis ==="
    echo "Pages URLs in data:"
    echo "$JSON" | grep -o '"url":"[^"]*"' | head -10
else
    echo "❌ page-data script tag NOT found"
fi

echo ""
echo "=== Check if URLs are correct ==="
echo "Looking for subdomain URLs (http:// or https://):"
FULL_URLS=$(grep -o '"url":"http[^"]*"' "$CATEGORY_FILE" | wc -l)
echo "Full URLs found: $FULL_URLS"

echo ""
echo "Looking for relative URLs (starting with /):"
REL_URLS=$(grep -o '"url":"\/[^h][^"]*"' "$CATEGORY_FILE" | wc -l)
echo "Relative URLs found: $REL_URLS"

echo ""
echo "Sample URLs:"
grep -o '"url":"[^"]*"' "$CATEGORY_FILE" | head -5
