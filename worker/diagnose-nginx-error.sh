#!/usr/bin/env bash
# Script to diagnose nginx reload errors

echo "=== Nginx Error Diagnostic ==="
echo ""

echo "1. Full nginx error logs (last 50 lines):"
echo "----------------------------------------"
tail -50 /var/log/nginx/error.log
echo ""

echo "2. Testing nginx configuration:"
echo "----------------------------------------"
nginx -t 2>&1
echo ""

echo "3. Checking what's listening on port 80 and 443:"
echo "----------------------------------------"
netstat -tlnp | grep -E ':80|:443' || ss -tlnp | grep -E ':80|:443'
echo ""

echo "4. Looking for duplicate server_name directives:"
echo "----------------------------------------"
echo "Checking for duplicate server blocks..."
grep -r "server_name" /etc/nginx/sites-enabled/ | sort | uniq -d
echo ""

echo "5. List all enabled sites:"
echo "----------------------------------------"
ls -lah /etc/nginx/sites-enabled/
echo ""

echo "6. Check for syntax errors in all configs:"
echo "----------------------------------------"
for conf in /etc/nginx/sites-enabled/*; do
    echo "Checking: $conf"
    nginx -t -c $conf 2>&1 | grep -i error || echo "  ✅ No errors"
done
echo ""

echo "7. Checking nginx service journal for recent errors:"
echo "----------------------------------------"
journalctl -u nginx -n 50 --no-pager
echo ""

echo "=== Diagnostic Complete ==="
echo ""
echo "Common fixes:"
echo "1. If port already in use: sudo fuser -k 80/tcp && sudo systemctl restart nginx"
echo "2. If duplicate server_name: Check and remove duplicate configs"
echo "3. If permission error: Check file permissions in /etc/nginx/"
