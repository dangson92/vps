#!/usr/bin/env bash
# Script to fix nginx configuration for long domain names on existing VPS workers

set -euo pipefail

echo "=== Fixing Nginx Configuration for Long Domain Names ==="
echo ""

# Check if already configured
if grep -q "server_names_hash_bucket_size" /etc/nginx/nginx.conf; then
  CURRENT_SIZE=$(grep "server_names_hash_bucket_size" /etc/nginx/nginx.conf | grep -oP '\d+' | head -1)
  echo "✅ Already configured with server_names_hash_bucket_size = $CURRENT_SIZE"
  echo ""
  read -p "Do you want to update it? (y/N): " -n 1 -r
  echo
  if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Skipping update."
    exit 0
  fi
  # Remove old configuration
  sed -i '/server_names_hash_bucket_size/d' /etc/nginx/nginx.conf
  sed -i '/# Support for long domain names/d' /etc/nginx/nginx.conf
fi

# Backup nginx.conf
echo "Creating backup of nginx.conf..."
cp /etc/nginx/nginx.conf /etc/nginx/nginx.conf.backup.$(date +%Y%m%d_%H%M%S)
echo "✅ Backup created"
echo ""

# Add configuration
echo "Adding server_names_hash_bucket_size configuration..."
sed -i '/http {/a \    # Support for long domain names\n    server_names_hash_bucket_size 128;' /etc/nginx/nginx.conf
echo "✅ Configuration added"
echo ""

# Test nginx configuration
echo "Testing nginx configuration..."
nginx -t

if [ $? -eq 0 ]; then
  echo "✅ Nginx configuration test passed"
  echo ""

  # Reload nginx
  echo "Reloading nginx..."
  systemctl reload nginx

  if [ $? -eq 0 ]; then
    echo "✅ Nginx reloaded successfully"
    echo ""
    echo "=== Configuration Complete ==="
    echo "Nginx is now configured to handle long domain names (up to 128 characters)"
  else
    echo "❌ Failed to reload nginx"
    echo "Restoring backup..."
    cp /etc/nginx/nginx.conf.backup.$(date +%Y%m%d)_* /etc/nginx/nginx.conf
    exit 1
  fi
else
  echo "❌ Nginx configuration test failed"
  echo "Restoring backup..."
  BACKUP_FILE=$(ls -t /etc/nginx/nginx.conf.backup.* | head -1)
  cp "$BACKUP_FILE" /etc/nginx/nginx.conf
  echo "Backup restored. Please check /etc/nginx/nginx.conf manually."
  exit 1
fi

# Show the added configuration
echo ""
echo "Added configuration:"
grep -A 1 "Support for long domain names" /etc/nginx/nginx.conf
