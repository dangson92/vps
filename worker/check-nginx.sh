#!/usr/bin/env bash
# Script to diagnose nginx configuration issues for a domain

if [ -z "$1" ]; then
  echo "Usage: $0 <domain>"
  echo "Example: $0 hotel.example.com"
  exit 1
fi

DOMAIN="$1"
DOMAIN_LOWER=$(echo "$DOMAIN" | tr '[:upper:]' '[:lower:]')

echo "=== Nginx Configuration Diagnostic for: $DOMAIN ==="
echo ""

# Check if config file exists in sites-available
echo "1. Checking /etc/nginx/sites-available/$DOMAIN_LOWER"
if [ -f "/etc/nginx/sites-available/$DOMAIN_LOWER" ]; then
  echo "   ✅ Config file exists"
  echo "   Content:"
  cat "/etc/nginx/sites-available/$DOMAIN_LOWER"
else
  echo "   ❌ Config file NOT found"
fi
echo ""

# Check if symlink exists in sites-enabled
echo "2. Checking /etc/nginx/sites-enabled/$DOMAIN_LOWER"
if [ -L "/etc/nginx/sites-enabled/$DOMAIN_LOWER" ]; then
  echo "   ✅ Symlink exists"
  echo "   Target: $(readlink -f /etc/nginx/sites-enabled/$DOMAIN_LOWER)"
elif [ -f "/etc/nginx/sites-enabled/$DOMAIN_LOWER" ]; then
  echo "   ⚠️  File exists but is NOT a symlink"
else
  echo "   ❌ Symlink NOT found"
fi
echo ""

# Check document root
if [ -f "/etc/nginx/sites-available/$DOMAIN_LOWER" ]; then
  DOC_ROOT=$(grep -oP 'root\s+\K[^;]+' "/etc/nginx/sites-available/$DOMAIN_LOWER" | head -1 | tr -d ' ')
  echo "3. Checking document root: $DOC_ROOT"
  if [ -d "$DOC_ROOT" ]; then
    echo "   ✅ Document root exists"
    echo "   Contents:"
    ls -lah "$DOC_ROOT" | head -15
  else
    echo "   ❌ Document root NOT found"
  fi
  echo ""
fi

# Test nginx configuration
echo "4. Testing nginx configuration"
nginx -t 2>&1
if [ $? -eq 0 ]; then
  echo "   ✅ Nginx config test passed"
else
  echo "   ❌ Nginx config test FAILED"
fi
echo ""

# Check nginx status
echo "5. Checking nginx service status"
systemctl status nginx --no-pager -l | head -20
echo ""

# Check nginx error log for this domain
echo "6. Checking recent nginx error log"
tail -20 /var/log/nginx/error.log | grep -i "$DOMAIN" || echo "   No recent errors for this domain"
echo ""

# Check listening ports
echo "7. Checking nginx listening ports"
netstat -tlnp | grep nginx || ss -tlnp | grep nginx
echo ""

echo "=== Diagnostic Complete ==="
