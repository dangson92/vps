#!/usr/bin/env bash
# Script to manually create/fix nginx configuration for a domain

if [ -z "$1" ] || [ -z "$2" ]; then
  echo "Usage: $0 <domain> <document_root>"
  echo "Example: $0 hotel.example.com /var/www/example.com/hotel"
  exit 1
fi

DOMAIN="$1"
DOC_ROOT="$2"
DOMAIN_LOWER=$(echo "$DOMAIN" | tr '[:upper:]' '[:lower:]')
CONFIG_FILE="/etc/nginx/sites-available/$DOMAIN_LOWER"
ENABLED_LINK="/etc/nginx/sites-enabled/$DOMAIN_LOWER"

echo "=== Creating/Fixing Nginx Config for: $DOMAIN ==="
echo "Domain: $DOMAIN"
echo "Document Root: $DOC_ROOT"
echo ""

# Create document root if it doesn't exist
if [ ! -d "$DOC_ROOT" ]; then
  echo "Creating document root directory..."
  mkdir -p "$DOC_ROOT"
  chmod 755 "$DOC_ROOT"
  echo "✅ Document root created"
fi

# Create a basic index.html if it doesn't exist
if [ ! -f "$DOC_ROOT/index.html" ]; then
  echo "Creating index.html..."
  cat > "$DOC_ROOT/index.html" <<EOF
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>$DOMAIN</title>
</head>
<body>
    <h1>Welcome to $DOMAIN</h1>
    <p>This is a test page.</p>
</body>
</html>
EOF
  chmod 644 "$DOC_ROOT/index.html"
  echo "✅ index.html created"
fi

# Create nginx config
echo "Creating nginx configuration..."
cat > "$CONFIG_FILE" <<EOF
server {
    listen 80;
    server_name $DOMAIN www.$DOMAIN;
    root $DOC_ROOT;
    index index.html index.htm index.php;

    location ^~ /.well-known/acme-challenge/ {
        root $DOC_ROOT;
        default_type "text/plain";
        try_files \$uri =404;
    }

    location / {
        try_files \$uri \$uri/ \$uri/index.html =404;
    }

    # Uncomment if using PHP
    # location ~ \.php$ {
    #     include snippets/fastcgi-php.conf;
    #     fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    # }
}
EOF

echo "✅ Config file created at $CONFIG_FILE"

# Create symlink to sites-enabled
if [ -L "$ENABLED_LINK" ]; then
  echo "Symlink already exists, removing old one..."
  rm "$ENABLED_LINK"
fi

ln -s "$CONFIG_FILE" "$ENABLED_LINK"
echo "✅ Symlink created in sites-enabled"

# Test nginx configuration
echo ""
echo "Testing nginx configuration..."
nginx -t

if [ $? -eq 0 ]; then
  echo "✅ Nginx config test passed"
  echo ""
  echo "Reloading nginx..."
  systemctl reload nginx

  if [ $? -eq 0 ]; then
    echo "✅ Nginx reloaded successfully"
    echo ""
    echo "=== Configuration Complete ==="
    echo "You can now access: http://$DOMAIN"
  else
    echo "❌ Failed to reload nginx"
  fi
else
  echo "❌ Nginx config test failed"
  echo "Please check the configuration manually"
fi
