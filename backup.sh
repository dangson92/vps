#!/bin/bash

BACKUP_DIR="/var/backups/vps-manager"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

# Backup database
mysqldump -u vps_manager -pa22f3fda13ee00562544c950e33aceb2 vps_manager > $BACKUP_DIR/database_$DATE.sql

# Backup websites
rsync -av /var/www/ $BACKUP_DIR/websites_$DATE/

# Backup nginx configs
cp -r /etc/nginx/sites-available $BACKUP_DIR/nginx_$DATE/

# Backup SSL certificates
cp -r /etc/letsencrypt $BACKUP_DIR/letsencrypt_$DATE/

# Compress backup
tar -czf $BACKUP_DIR/backup_$DATE.tar.gz -C $BACKUP_DIR database_$DATE.sql websites_$DATE nginx_$DATE letsencrypt_$DATE

# Remove uncompressed files
rm -rf $BACKUP_DIR/database_$DATE.sql $BACKUP_DIR/websites_$DATE $BACKUP_DIR/nginx_$DATE $BACKUP_DIR/letsencrypt_$DATE

# Keep only last 7 days of backups
find $BACKUP_DIR -name "backup_*.tar.gz" -mtime +7 -delete

echo "Backup completed: $BACKUP_DIR/backup_$DATE.tar.gz"
