#!/bin/bash

if [ $# -ne 1 ]; then
    echo "Usage: $0 <backup_file>"
    exit 1
fi

BACKUP_FILE=$1
BACKUP_DIR="/tmp/vps-restore"

if [ ! -f "$BACKUP_FILE" ]; then
    echo "Backup file not found: $BACKUP_FILE"
    exit 1
fi

# Extract backup
mkdir -p $BACKUP_DIR
tar -xzf $BACKUP_FILE -C $BACKUP_DIR

# Restore database
mysql -u vps_manager -pa22f3fda13ee00562544c950e33aceb2 vps_manager < $BACKUP_DIR/database_*.sql

# Restore websites
rsync -av $BACKUP_DIR/websites_*/ /var/www/

# Restore nginx configs
cp -r $BACKUP_DIR/nginx_*/sites-available/* /etc/nginx/sites-available/

# Restore SSL certificates
cp -r $BACKUP_DIR/letsencrypt_*/ /etc/letsencrypt/

# Reload services
systemctl reload nginx

echo "Restore completed from: $BACKUP_FILE"

# Clean up
rm -rf $BACKUP_DIR
