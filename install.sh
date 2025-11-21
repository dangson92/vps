#!/bin/bash

# VPS Manager Installation Script
# This script sets up the VPS Manager system on Ubuntu/Debian servers

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
MASTER_DOMAIN="${MASTER_DOMAIN:-}"
WORKER_KEY="${WORKER_KEY:-}"
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-$(openssl rand -base64 32)}"
APP_DB_PASSWORD="${APP_DB_PASSWORD:-$(openssl rand -hex 16)}"

print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   print_error "This script must be run as root"
   exit 1
fi

# Detect OS
if [[ -f /etc/os-release ]]; then
    . /etc/os-release
    OS=$NAME
    VER=$VERSION_ID
else
    print_error "Cannot detect OS"
    exit 1
fi

print_status "Detected OS: $OS $VER"

# Update system
print_status "Updating system packages..."
apt update && apt upgrade -y

# Function to install PHP with version fallback
install_php() {
    local php_version=$1
    print_status "Trying to install PHP ${php_version}..."
    
    # Try to install PHP packages
    if apt install -y php${php_version}-cli php${php_version}-fpm php${php_version}-mysql php${php_version}-curl php${php_version}-gd php${php_version}-mbstring php${php_version}-xml php${php_version}-zip php${php_version}-bcmath 2>/dev/null; then
        print_status "PHP ${php_version} installed successfully"
        return 0
    else
        print_warning "PHP ${php_version} not available"
        return 1
    fi
}

# Function to add PHP repository
add_php_repository() {
    print_status "Adding PHP repository..."
    
    # Install software-properties-common if not available
    apt install -y software-properties-common gnupg2 wget ca-certificates
    
    # Detect OS type and add appropriate repository
    if [[ "$OS" == *"Ubuntu"* ]]; then
        print_status "Detected Ubuntu, adding Ondřej Surý PPA..."
        if command -v add-apt-repository >/dev/null 2>&1; then
            add-apt-repository -y ppa:ondrej/php 2>/dev/null || {
                print_warning "Could not add PPA via add-apt-repository, trying manual method..."
                echo "deb http://ppa.launchpad.net/ondrej/php/ubuntu $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/php.list
                apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 4F4EA0AAE5267A6C 2>/dev/null || true
            }
        else
            # Manual PPA addition
            echo "deb http://ppa.launchpad.net/ondrej/php/ubuntu $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/php.list
            apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 4F4EA0AAE5267A6C 2>/dev/null || true
        fi
    elif [[ "$OS" == *"Debian"* ]]; then
        print_status "Detected Debian, adding Sury repository..."
        # For Debian, use Sury repository
        echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/php.list
        wget -qO - https://packages.sury.org/php/apt.gpg | apt-key add - 2>/dev/null || {
            print_warning "Could not add GPG key, trying alternative method..."
            wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg
        }
    else
        print_warning "Unknown OS type: $OS. Trying generic Sury repository..."
        echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/php.list
        wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg
    fi
    
    apt update
}

# Install PHP with version detection and fallback
install_php_with_fallback() {
    # First, try to add PHP repository
    add_php_repository
    
    # List of PHP versions to try (newest first)
    local php_versions=("8.3" "8.2" "8.1" "8.0" "7.4")
    local installed_version=""
    
    for version in "${php_versions[@]}"; do
        if install_php "$version"; then
            installed_version=$version
            break
        fi
    done
    
    # If no PHP version was installed, try to use system PHP
    if [[ -z "$installed_version" ]]; then
        print_warning "Could not install modern PHP versions. Checking for system PHP..."
        
        # Check if PHP is already installed
        if command -v php >/dev/null 2>&1; then
            local system_php_version=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2)
            print_status "Found system PHP version: $system_php_version"
            
            # Try to install extensions for system PHP
            if apt install -y php-mysql php-curl php-gd php-mbstring php-xml php-zip php-bcmath 2>/dev/null; then
                installed_version=$system_php_version
                print_status "Using system PHP with extensions installed"
            else
                print_error "Could not install PHP extensions for system PHP"
                exit 1
            fi
        else
            print_error "Could not install any PHP version and no system PHP found. Please install PHP manually."
            exit 1
        fi
    fi
    
    # Update nginx configuration with correct PHP version
    PHP_VERSION=$installed_version
    print_status "Using PHP version: ${installed_version}"

    # Ensure PHP CLI points to the installed version
    if command -v php${PHP_VERSION} >/dev/null 2>&1; then
        update-alternatives --set php /usr/bin/php${PHP_VERSION} 2>/dev/null || ln -sf /usr/bin/php${PHP_VERSION} /usr/bin/php
        print_status "PHP CLI set to php${PHP_VERSION}"
    fi
}

# Install required packages
print_status "Installing required packages..."
apt install -y \
    nginx \
    mysql-server \
    composer \
    curl \
    wget \
    unzip \
    git \
    certbot \
    python3-certbot-nginx \
    supervisor \
    cron \
    software-properties-common \
    gnupg2 \
    lsb-release

# Install PHP with fallback
install_php_with_fallback

# Install Node.js and npm
print_status "Installing Node.js..."
curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
apt install -y nodejs

# Function to configure MySQL with Amazon VPS support
configure_mysql() {
    print_status "Configuring MySQL..."
    
    # Check if MySQL is running
    if ! systemctl is-active --quiet mysql; then
        print_status "Starting MySQL service..."
        systemctl start mysql
        sleep 5
    fi
    
    # Test connection methods
    local mysql_cmd=""
    local mysql_password_set=false
    
    # Method 1: Try with sudo (Amazon Ubuntu default)
    print_status "Trying to connect with sudo..."
    if sudo mysql -e "SELECT 1;" 2>/dev/null; then
        print_status "✅ Connected with sudo!"
        mysql_cmd="sudo mysql"
        
        # Set root password
        print_status "Setting MySQL root password..."
        sudo mysql << EOF
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${MYSQL_ROOT_PASSWORD}';
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
FLUSH PRIVILEGES;
EOF
        mysql_password_set=true
    fi
    
    # Method 2: Try with empty password
    if [[ "$mysql_password_set" == false ]]; then
        print_status "Trying to connect with empty password..."
        if mysql -u root -e "SELECT 1;" 2>/dev/null; then
            print_status "✅ Connected with empty password!"
            mysql_cmd="mysql -u root"
            
            # Set root password
            mysql -u root << EOF
ALTER USER 'root'@'localhost' IDENTIFIED BY '${MYSQL_ROOT_PASSWORD}';
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
FLUSH PRIVILEGES;
EOF
            mysql_password_set=true
        fi
    fi
    
    # Method 3: Use mysql_secure_installation
    if [[ "$mysql_password_set" == false ]]; then
        print_warning "Using mysql_secure_installation to configure MySQL..."
        
        # Stop MySQL and start with skip-grant-tables
        systemctl stop mysql
        mysqld_safe --skip-grant-tables --skip-networking &
        sleep 5
        
        # Reset root password
        mysql << EOF
UPDATE mysql.user SET authentication_string=PASSWORD('temp123'), plugin='mysql_native_password' WHERE User='root' AND Host='localhost';
FLUSH PRIVILEGES;
EOF
        
        # Stop safe mode and start normally
        pkill mysqld
        sleep 3
        systemctl start mysql
        sleep 3
        
        # Login with temp password and set new password
        mysql -u root -ptemp123 << EOF
ALTER USER 'root'@'localhost' IDENTIFIED BY '${MYSQL_ROOT_PASSWORD}';
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
FLUSH PRIVILEGES;
EOF
        mysql_password_set=true
    fi
    
    # Test final connection
    if [[ "$mysql_password_set" == true ]]; then
        print_status "Testing MySQL connection with new password..."
        if mysql -u root -p"${MYSQL_ROOT_PASSWORD}" -e "SELECT 1;" 2>/dev/null; then
            print_status "✅ MySQL configured successfully!"
        else
            print_error "❌ MySQL connection test failed!"
            return 1
        fi
    else
        print_error "❌ Could not configure MySQL!"
        return 1
    fi
    
    # Save password for future reference
    echo "${MYSQL_ROOT_PASSWORD}" > /root/mysql_root_password.txt
    chmod 600 /root/mysql_root_password.txt
    print_status "MySQL root password saved to /root/mysql_root_password.txt"
}

if systemctl is-active --quiet mysql; then
    print_status "Skipping MySQL configuration (already running)"
else
    configure_mysql
fi

# Create VPS Manager user
print_status "Creating VPS Manager user..."
if ! id -u vps-manager >/dev/null 2>&1; then
    useradd -m -s /bin/bash vps-manager
fi
usermod -aG www-data vps-manager || true

# Create directory structure
print_status "Creating directory structure..."
mkdir -p /opt/vps-manager
mkdir -p /var/www/vps-manager
mkdir -p /etc/vps-worker
mkdir -p /var/log/vps-worker

# Clone or copy VPS Manager files
if [[ -d "/vagrant" ]]; then
    print_status "Copying files from /vagrant..."
    cp -r /vagrant/* /opt/vps-manager/
else
    if [ -d "/opt/vps-manager/app" ] || [ -f "/opt/vps-manager/composer.json" ]; then
        print_status "Project files already present in /opt/vps-manager"
    else
        print_status "Please copy VPS Manager files to /opt/vps-manager"
        print_warning "Files not found. Please manually copy the project files to /opt/vps-manager"
    fi
fi

# Set permissions
chown -R vps-manager:www-data /opt/vps-manager
chown -R vps-manager:www-data /var/www/vps-manager
chown -R vps-manager:www-data /etc/vps-worker
chown -R vps-manager:www-data /var/log/vps-worker
chmod -R 755 /opt/vps-manager
chmod -R 755 /var/www/vps-manager

# Laravel writable directories
print_status "Setting Laravel writable permissions..."
mkdir -p /opt/vps-manager/storage/framework/{cache,sessions,views,testing}
mkdir -p /opt/vps-manager/storage/logs
mkdir -p /opt/vps-manager/bootstrap/cache
chown -R www-data:www-data /opt/vps-manager/storage /opt/vps-manager/bootstrap/cache
chmod -R 775 /opt/vps-manager/storage /opt/vps-manager/bootstrap/cache

# Install PHP dependencies
print_status "Installing PHP dependencies..."
cd /opt/vps-manager
if [ -d "vendor" ]; then
    print_status "PHP dependencies already installed"
else
    sudo -u vps-manager composer install --no-dev --optimize-autoloader || sudo -u vps-manager composer install --no-dev --optimize-autoloader --ignore-platform-req=ext-dom || true
fi

# Ensure required PHP extensions (dom, pdo_mysql)
print_status "Ensuring required PHP extensions..."
if ! php -m | grep -qi dom; then
    apt-get update -y || true
    apt-get install -y php${PHP_VERSION}-xml || apt-get install -y php-xml || true
fi
if ! php -m | grep -qi pdo_mysql; then
    apt-get update -y || true
    apt-get install -y php${PHP_VERSION}-mysql || apt-get install -y php-mysql || true
fi
systemctl restart php${PHP_VERSION}-fpm || true

# Install Node.js dependencies and build frontend
print_status "Building frontend..."
cd /opt/vps-manager/frontend
if [ -d "node_modules" ]; then
    sudo -u vps-manager npm ci || sudo -u vps-manager npm install
else
    sudo -u vps-manager npm install
fi
sudo -u vps-manager npm run build || true

# Create Laravel environment file
print_status "Creating Laravel environment file..."
cd /opt/vps-manager
if [ ! -f .env ]; then cp .env.example .env; fi

# Generate Laravel key
sudo -u vps-manager php artisan key:generate || true

# Update environment file
sed -i "s/DB_USERNAME=.*/DB_USERNAME=vps_manager/" .env
sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=${APP_DB_PASSWORD}/" .env
SANITIZED_APP_DOMAIN=$(echo "${MASTER_DOMAIN}" | sed -E 's#https?://##; s#/.*##; s#\s+##g')
APP_URL_VALUE="${SANITIZED_APP_DOMAIN:+http://${SANITIZED_APP_DOMAIN}}"
if [ -z "$APP_URL_VALUE" ]; then APP_URL_VALUE="http://localhost"; fi
sed -i "s|APP_URL=.*|APP_URL=${APP_URL_VALUE}|" .env
sed -i "s/WORKER_SECRET_KEY=your-worker-secret-key/WORKER_SECRET_KEY=${WORKER_KEY}/" .env

# Create systemd service for VPS Worker
print_status "Creating VPS Worker service..."
if [ ! -f /etc/systemd/system/vps-worker.service ]; then
cat > /etc/systemd/system/vps-worker.service << EOF
[Unit]
Description=VPS Manager Worker Node
After=network.target

[Service]
Type=simple
User=vps-manager
Group=www-data
WorkingDirectory=/opt/vps-manager/worker
ExecStart=/usr/bin/php worker.php start
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
EOF
fi

# Create nginx configuration
print_status "Creating nginx configuration..."
# Sanitize MASTER_DOMAIN (strip protocol and path)
SANITIZED_DOMAIN=$(echo "${MASTER_DOMAIN}" | sed -E 's#https?://##; s#/.*##; s#\s+##g')
DOMAIN_VALUE="${SANITIZED_DOMAIN:-_}"

# Always (re)write nginx site config with sanitized domain (backup if exists)
if [ -f /etc/nginx/sites-available/vps-manager ]; then
    cp /etc/nginx/sites-available/vps-manager /etc/nginx/sites-available/vps-manager.bak || true
fi

cat > /etc/nginx/sites-available/vps-manager << EOF
server {
    listen 80;
    server_name ${DOMAIN_VALUE};
    root /opt/vps-manager/public;
    index index.php index.html;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:/var/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location /api/worker {
        allow 127.0.0.1;
        allow ::1;
        deny all;
    }

    location ~ /\.ht {
        deny all;
    }
}
EOF

# Enable nginx site
ln -sf /etc/nginx/sites-available/vps-manager /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx || true

# Create cron jobs for monitoring
print_status "Setting up monitoring cron jobs..."
cat > /etc/cron.d/vps-manager << EOF
# VPS Manager monitoring and maintenance
*/5 * * * * vps-manager cd /opt/vps-manager && php artisan monitoring:check >> /var/log/vps-manager/monitoring.log 2>&1
0 2 * * * vps-manager cd /opt/vps-manager && php artisan ssl:renew >> /var/log/vps-manager/ssl-renew.log 2>&1
0 1 * * * vps-manager cd /opt/vps-manager && php artisan logs:parse >> /var/log/vps-manager/logs-parse.log 2>&1
EOF

# Create log rotation
print_status "Setting up log rotation..."
cat > /etc/logrotate.d/vps-manager << EOF
/var/log/vps-manager/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 vps-manager www-data
    sharedscripts
    postrotate
        systemctl reload vps-worker.service || true
    endscript
}

/var/log/vps-worker/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 vps-manager www-data
}
EOF

# Create Laravel commands
print_status "Creating Laravel commands..."
mkdir -p /opt/vps-manager/app/Console/Commands

# Create monitoring command
cat > /opt/vps-manager/app/Console/Commands/CheckMonitoring.php << 'EOF'
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MonitoringService;
use App\Models\Website;

class CheckMonitoring extends Command
{
    protected $signature = 'monitoring:check';
    protected $description = 'Check website uptime and record statistics';

    public function handle(MonitoringService $monitoringService)
    {
        $websites = Website::where('status', 'deployed')->get();
        
        foreach ($websites as $website) {
            try {
                $monitoringService->recordStats($website);
                $this->info("Checked {$website->domain}");
            } catch (\Exception $e) {
                $this->error("Failed to check {$website->domain}: " . $e->getMessage());
            }
        }
        
        return Command::SUCCESS;
    }
}
EOF

# Create SSL renewal command
cat > /opt/vps-manager/app/Console/Commands/RenewSsl.php << 'EOF'
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Website;
use App\Services\SslService;
use Carbon\Carbon;

class RenewSsl extends Command
{
    protected $signature = 'ssl:renew';
    protected $description = 'Renew expiring SSL certificates';

    public function handle(SslService $sslService)
    {
        $expiringWebsites = Website::where('ssl_enabled', true)
            ->where('ssl_expires_at', '<=', Carbon::now()->addDays(30))
            ->get();
        
        foreach ($expiringWebsites as $website) {
            try {
                $sslService->renew($website);
                $this->info("Renewed SSL for {$website->domain}");
            } catch (\Exception $e) {
                $this->error("Failed to renew SSL for {$website->domain}: " . $e->getMessage());
            }
        }
        
        return Command::SUCCESS;
    }
}
EOF

# Create log parsing command
cat > /opt/vps-manager/app/Console/Commands/ParseLogs.php << 'EOF'
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Website;
use App\Services\MonitoringService;

class ParseLogs extends Command
{
    protected $signature = 'logs:parse';
    protected $description = 'Parse nginx logs for statistics';

    public function handle(MonitoringService $monitoringService)
    {
        $websites = Website::where('status', 'deployed')->get();
        
        foreach ($websites as $website) {
            try {
                $monitoringService->recordStats($website);
                $this->info("Parsed logs for {$website->domain}");
            } catch (\Exception $e) {
                $this->error("Failed to parse logs for {$website->domain}: " . $e->getMessage());
            }
        }
        
        return Command::SUCCESS;
    }
}
EOF

# Register commands in Kernel
print_status "Registering Laravel commands..."
print_status "Laravel commands directory is auto-loaded by Kernel"

# Set up database
print_status "Setting up database..."
sudo mysql << EOF
CREATE DATABASE IF NOT EXISTS vps_manager;
CREATE USER IF NOT EXISTS 'vps_manager'@'localhost' IDENTIFIED WITH mysql_native_password BY '${APP_DB_PASSWORD}';
CREATE USER IF NOT EXISTS 'vps_manager'@'127.0.0.1' IDENTIFIED WITH mysql_native_password BY '${APP_DB_PASSWORD}';
GRANT ALL PRIVILEGES ON vps_manager.* TO 'vps_manager'@'localhost';
GRANT ALL PRIVILEGES ON vps_manager.* TO 'vps_manager'@'127.0.0.1';
FLUSH PRIVILEGES;
EOF
mysql -u vps_manager -h 127.0.0.1 -p"${APP_DB_PASSWORD}" -e "SELECT 1;" >/dev/null 2>&1 || print_warning "Database login check failed for vps_manager@127.0.0.1"
if [ -f /opt/vps-manager/database/schema.sql ]; then
    mysql -u vps_manager -p"${APP_DB_PASSWORD}" vps_manager < /opt/vps-manager/database/schema.sql || true
fi

# Run Laravel migrations
print_status "Running Laravel migrations..."
cd /opt/vps-manager
sudo -u vps-manager php artisan migrate --force || true

# Start services
print_status "Starting services..."
systemctl enable vps-worker
systemctl start vps-worker
systemctl enable nginx
systemctl start nginx
systemctl enable cron
systemctl daemon-reload || true

# Create backup script
print_status "Creating backup script..."
if [ ! -f /opt/vps-manager/backup.sh ]; then
cat > /opt/vps-manager/backup.sh << EOF
#!/bin/bash

BACKUP_DIR="/var/backups/vps-manager"
DATE=\$(date +%Y%m%d_%H%M%S)

mkdir -p \$BACKUP_DIR

# Backup database
mysqldump -u vps_manager -p${APP_DB_PASSWORD} vps_manager > \$BACKUP_DIR/database_\$DATE.sql

# Backup websites
rsync -av /var/www/ \$BACKUP_DIR/websites_\$DATE/

# Backup nginx configs
cp -r /etc/nginx/sites-available \$BACKUP_DIR/nginx_\$DATE/

# Backup SSL certificates
cp -r /etc/letsencrypt \$BACKUP_DIR/letsencrypt_\$DATE/

# Compress backup
tar -czf \$BACKUP_DIR/backup_\$DATE.tar.gz -C \$BACKUP_DIR database_\$DATE.sql websites_\$DATE nginx_\$DATE letsencrypt_\$DATE

# Remove uncompressed files
rm -rf \$BACKUP_DIR/database_\$DATE.sql \$BACKUP_DIR/websites_\$DATE \$BACKUP_DIR/nginx_\$DATE \$BACKUP_DIR/letsencrypt_\$DATE

# Keep only last 7 days of backups
find \$BACKUP_DIR -name "backup_*.tar.gz" -mtime +7 -delete

echo "Backup completed: \$BACKUP_DIR/backup_\$DATE.tar.gz"
EOF
fi

chmod +x /opt/vps-manager/backup.sh

# Add backup to cron
echo "0 3 * * * root /opt/vps-manager/backup.sh >> /var/log/vps-manager/backup.log 2>&1" >> /etc/cron.d/vps-manager

# Create restore script
print_status "Creating restore script..."
if [ ! -f /opt/vps-manager/restore.sh ]; then
cat > /opt/vps-manager/restore.sh << EOF
#!/bin/bash

if [ \$# -ne 1 ]; then
    echo "Usage: \$0 <backup_file>"
    exit 1
fi

BACKUP_FILE=\$1
BACKUP_DIR="/tmp/vps-restore"

if [ ! -f "\$BACKUP_FILE" ]; then
    echo "Backup file not found: \$BACKUP_FILE"
    exit 1
fi

# Extract backup
mkdir -p \$BACKUP_DIR
tar -xzf \$BACKUP_FILE -C \$BACKUP_DIR

# Restore database
mysql -u vps_manager -p${APP_DB_PASSWORD} vps_manager < \$BACKUP_DIR/database_*.sql

# Restore websites
rsync -av \$BACKUP_DIR/websites_*/ /var/www/

# Restore nginx configs
cp -r \$BACKUP_DIR/nginx_*/sites-available/* /etc/nginx/sites-available/

# Restore SSL certificates
cp -r \$BACKUP_DIR/letsencrypt_*/ /etc/letsencrypt/

# Reload services
systemctl reload nginx

echo "Restore completed from: \$BACKUP_FILE"

# Clean up
rm -rf \$BACKUP_DIR
EOF
fi

chmod +x /opt/vps-manager/restore.sh

# Create systemd service for Laravel queue worker
print_status "Creating queue worker service..."
if [ ! -f /etc/systemd/system/vps-queue-worker.service ]; then
cat > /etc/systemd/system/vps-queue-worker.service << EOF
[Unit]
Description=VPS Manager Queue Worker
After=network.target

[Service]
Type=simple
User=vps-manager
Group=www-data
WorkingDirectory=/opt/vps-manager
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --timeout=60
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
EOF
fi

# Enable and start queue worker
systemctl enable vps-queue-worker
systemctl start vps-queue-worker

# Final configuration
print_status "Final configuration..."

# Set proper permissions
chown -R vps-manager:www-data /opt/vps-manager
chown -R vps-manager:www-data /var/www/vps-manager
chmod -R 755 /opt/vps-manager
chmod -R 755 /var/www/vps-manager

# Create status script
cat > /usr/local/bin/vps-status << 'EOF'
#!/bin/bash
echo "=== VPS Manager Status ==="
echo "Worker Service: $(systemctl is-active vps-worker)"
echo "Nginx Service: $(systemctl is-active nginx)"
echo "Queue Worker: $(systemctl is-active vps-queue-worker)"
echo ""
echo "=== Recent Logs ==="
tail -n 10 /var/log/vps-worker/worker.log
echo ""
echo "=== Worker Key ==="
cat /etc/vps-worker/config.json | grep worker_key
echo ""
echo "=== Websites ==="
ls -la /var/www/ | tail -n 10
EOF

chmod +x /usr/local/bin/vps-status

# Save installation log
print_status "Saving installation log..."
{
    echo "=== VPS Manager Installation Log ==="
    echo "Date: $(date)"
    echo "OS: $OS $VER"
    echo "PHP Version: ${PHP_VERSION}"
    echo "MySQL Root Password: ${MYSQL_ROOT_PASSWORD}"
    echo "Worker Key: ${WORKER_KEY}"
    echo ""
    echo "=== Service Status ==="
    systemctl is-active vps-worker
    systemctl is-active nginx
    systemctl is-active vps-queue-worker
} > /var/log/vps-manager-install.log

print_status "Installation completed!"
echo ""
echo "=== IMPORTANT INFORMATION ==="
echo "MySQL Root Password: ${MYSQL_ROOT_PASSWORD}"
echo "Worker Key: ${WORKER_KEY}"
echo "PHP Version: ${PHP_VERSION}"
echo ""
echo "=== NEXT STEPS ==="
echo "1. Configure your domain DNS to point to this server"
echo "2. Run 'certbot --nginx -d ${MASTER_DOMAIN}' to enable SSL"
echo "3. Access the web interface at http://${MASTER_DOMAIN}"
echo "4. Add VPS servers using the worker key above"
echo "5. Configure Cloudflare API tokens in the .env file"
echo ""
echo "=== USEFUL COMMANDS ==="
echo "Check status: vps-status"
echo "View logs: tail -f /var/log/vps-worker/worker.log"
echo "Backup: /opt/vps-manager/backup.sh"
echo "Restore: /opt/vps-manager/restore.sh <backup_file>"
echo ""
echo "Installation log saved to: /var/log/vps-manager-install.log"