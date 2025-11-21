# VPS Manager - Complete Setup Guide

A comprehensive VPS management system with master server and worker nodes, supporting HTML/WordPress deployment, DNS management, SSL certificates, and monitoring.

## 🚨 QUICK FIXES FOR COMMON ISSUES

### Issue 1: PHP Installation Failed (Ubuntu/Debian)
```bash
# Fix PHP repository and install with fallback
sudo apt install software-properties-common -y
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Try PHP 8.2 first, fallback to 8.1 if needed
sudo apt install php8.2-fpm php8.2-{mysql,curl,gd,mbstring,xml,zip,bcmath} || \
sudo apt install php8.1-fpm php8.1-{mysql,curl,gd,mbstring,xml,zip,bcmath}
```

### Issue 2: MySQL Access Denied (Amazon VPS)
```bash
# Download and run MySQL fix script
wget https://dangthanhson.com/vps/fix-mysql-password.sh
chmod +x fix-mysql-password.sh
sudo ./fix-mysql-password.sh

# Continue installation
cd /opt/vps-manager
sudo ./install.sh
```

### Issue 3: Composer Dependencies Failed
```bash
# Fix composer.json and reinstall
cd /opt/vps-manager
rm -rf vendor composer.lock
composer install --no-dev --optimize-autoloader
```

### Issue 4: Missing Artisan File
```bash
# Ensure artisan exists and has proper permissions
chmod +x artisan
php artisan key:generate
php artisan config:cache
```

## 🚀 Quick Start (5 minutes)

### Prerequisites
- Master Server: Ubuntu 20.04+ / Debian 10+ (2GB RAM, 20GB disk)
- Domain name with DNS management (Cloudflare recommended)
- Root/sudo access to servers

### 1. Master Server Setup
```bash
# Connect to your master server
ssh root@your-master-server

# Update system
apt update && apt upgrade -y

# Install prerequisites
apt install -y curl wget git unzip

# Download and run installer
wget https://dangthanhson.com/vps/install.sh
chmod +x install.sh
sudo ./install.sh

# Access dashboard at: https://your-domain.com
```

### 2. Worker Server Setup
```bash
# Connect to worker VPS
ssh root@your-worker-vps

# Update system
apt update && apt upgrade -y

# Download worker setup
wget https://dangthanhson.com/vps/worker-setup.sh
chmod +x worker-setup.sh
sudo ./worker-setup.sh

# Get worker key from master dashboard
# Add worker in master dashboard → VPS Servers → Add New
```

### 3. Deploy First Website
1. Login to master dashboard
2. Click "Websites" → "Add Website"
3. Choose HTML or WordPress
4. Enter domain and select VPS
5. Click "Deploy" (wait 30-60 seconds)
6. Access your website!

---

## 📋 Detailed Documentation

### Table of Contents
1. [System Architecture](#system-architecture)
2. [Master Server Setup](#master-server-setup)
3. [Worker Server Setup](#worker-server-setup)
4. [Website Deployment](#website-deployment)
5. [Troubleshooting](#troubleshooting)
6. [Security](#security)
7. [Advanced Configuration](#advanced-configuration)

---

## 🏗️ System Architecture

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   User Browser  │    │  Cloudflare DNS │    │  Master Server  │
│                 │    │                 │    │  (Dashboard)    │
└─────────┬───────┘    └─────────┬───────┘    └─────────┬───────┘
          │                      │                      │
          └──────────────────────┼──────────────────────┘
                                 │
                    ┌────────────┴────────────┐
                    │                           │
            ┌───────▼───────┐          ┌────────▼────────┐
            │ Worker VPS 1  │          │  Worker VPS 2   │
            │ (10.0.1.101)  │          │  (10.0.1.102)   │
            └───────────────┘          └─────────────────┘
```

**Components:**
- **Master Server**: Laravel + Vue.js dashboard
- **Worker Nodes**: PHP CLI agents
- **Database**: MySQL for data storage
- **Web Server**: Nginx for all websites
- **DNS**: Cloudflare API integration
- **SSL**: Let's Encrypt automation

---

## 🖥️ Master Server Setup

### Step 1: Server Preparation
```bash
# Update system
apt update && apt upgrade -y

# Install prerequisites
apt install -y curl wget git unzip
```

### Step 2: Upload Code
Choose one method:

**Option A - Direct Download:**
```bash
cd /opt
wget https://dangthanhson.com/vps/vps-manager-latest.zip
unzip vps-manager-latest.zip
cd vps-manager
```

**Option B - Git Clone:**
```bash
cd /opt
git clone https://github.com/yourusername/vps-manager.git
cd vps-manager
```

**Option C - SCP Upload:**
```bash
# From local machine
scp -r vps-manager/ root@your-server:/opt/

# On server
cd /opt/vps-manager
```

### Step 3: Domain Configuration
Point your domain to master server IP:
```
Type: A Record
Name: vps (or your subdomain)
Value: YOUR_MASTER_SERVER_IP
TTL: Auto
```

Example: `vps.yourdomain.com` → `10.0.0.100`

### Step 4: Run Installation
```bash
# Make executable
chmod +x install.sh

# Run with your domain
MASTER_DOMAIN=vps.yourdomain.com WORKER_KEY=your-secret-key sudo ./install.sh

# Or run interactively (will prompt for details)
sudo ./install.sh
```

**Installation includes:**
- ✅ Nginx web server
- ✅ MySQL database
- ✅ PHP 8.x with all extensions
- ✅ Laravel backend
- ✅ Vue.js frontend
- ✅ SSL certificates
- ✅ System services
- ✅ Monitoring tools

### Step 5: Post-Installation
```bash
# Check installation log
cat /var/log/vps-manager-install.log

# Test services
vps-status

# Get worker key
cat /etc/vps-worker/config.json | grep worker_key
```

### Step 6: Cloudflare Configuration
```bash
# Edit environment file
cd /opt/vps-manager
nano .env

# Add Cloudflare credentials
CLOUDFLARE_EMAIL=your-email@domain.com
CLOUDFLARE_API_KEY=your-global-api-key
CLOUDFLARE_ZONE_ID=your-zone-id
```

Get API key from: [Cloudflare Profile](https://dash.cloudflare.com/profile/api-tokens)

### Step 7: Access Dashboard
- URL: `https://your-domain.com`
- Default credentials in `.env` file
- Change password immediately!

---

## 🔧 Worker Server Setup

### Step 1: Server Preparation
```bash
# Update system
apt update && apt upgrade -y

# Install prerequisites
apt install -y curl wget git unzip nginx mysql-server php-cli php-mysql
```

### Step 2: Download Worker Files
```bash
cd /opt
wget https://dangthanhson.com/vps/worker.zip
unzip worker.zip
cd vps-worker
```

### Step 3: Configuration
```bash
# Copy config template
cp config.json.example config.json

# Edit configuration
nano config.json
```

**Config file:**
```json
{
    "master_url": "https://your-master-domain.com",
    "worker_key": "worker-key-from-master-dashboard",
    "mysql_host": "localhost",
    "mysql_user": "root",
    "mysql_password": "your-mysql-password",
    "web_root": "/var/www",
    "nginx_config_dir": "/etc/nginx/sites-available",
    "log_file": "/var/log/vps-worker/worker.log"
}
```

### Step 4: Install Worker Service
```bash
# Install service
cp vps-worker.service /etc/systemd/system/
chmod +x worker.php

# Enable and start
systemctl enable vps-worker
systemctl start vps-worker

# Check status
systemctl status vps-worker
```

### Step 5: Test Connection
```bash
# Test API connection
curl -H "X-Worker-Key: your-key" https://master-domain.com/api/worker/status

# Check logs
tail -f /var/log/vps-worker/worker.log
```

---

## 🌐 Website Deployment

### HTML Website
1. **Dashboard** → Websites → Add Website
2. **Type**: HTML
3. **Domain**: `site.yourdomain.com`
4. **VPS**: Select worker server
5. **Template**: Choose or upload custom
6. **Deploy**: Click deploy button

### WordPress Website
1. **Dashboard** → Websites → Add Website
2. **Type**: WordPress
3. **Domain**: `blog.yourdomain.com`
4. **Title**: Site Title
5. **Admin**: Admin username/email
6. **Template**: Choose WordPress template
7. **Deploy**: Wait 60-90 seconds

### Custom Deployment
- Upload custom templates
- Configure PHP settings
- Set up databases
- Configure SSL certificates
- Setup monitoring

---

## 🔍 Troubleshooting

### Master Server Issues

#### MySQL Access Denied (Amazon VPS)
```bash
# Run fix script
wget https://dangthanhson.com/vps/fix-mysql-password.sh
chmod +x fix-mysql-password.sh
sudo ./fix-mysql-password.sh

# Then continue installation
cd /opt/vps-manager
sudo ./install.sh
```

#### PHP Installation Failed
```bash
# Check PHP version
php -v

# Manual PHP install with repository
sudo apt install software-properties-common
sudo add-apt-repository ppa:ondrej/php
sudo apt update

# Install PHP with fallback versions
sudo apt install php8.2-fpm php8.2-{mysql,curl,gd,mbstring,xml,zip,bcmath} || \
sudo apt install php8.1-fpm php8.1-{mysql,curl,gd,mbstring,xml,zip,bcmath} || \
sudo apt install php8.0-fpm php8.0-{mysql,curl,gd,mbstring,xml,zip,bcmath}
```

#### Composer Dependencies Failed
```bash
# Fix composer.json
cd /opt/vps-manager

# Update Cloudflare SDK version
sed -i 's/"cloudflare\/cloudflare": "^2.0"/"cloudflare\/sdk": "^1.4"/' composer.json

# Clear and reinstall
rm -rf vendor composer.lock
composer install --no-dev --optimize-autoloader
```

#### Artisan Missing or Permissions
```bash
# Ensure artisan exists
ls -la artisan

# Fix permissions
chmod +x artisan

# Generate key if missing
php artisan key:generate

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

#### Nginx Configuration Error
```bash
# Test nginx config
nginx -t

# Check error logs
tail -f /var/log/nginx/error.log

# Restart nginx
systemctl restart nginx
```

### Worker Server Issues

#### Connection Failed
```bash
# Test API connection
curl -H "X-Worker-Key: your-key" https://master/api/worker/status

# Check worker logs
tail -f /var/log/vps-worker/worker.log

# Test MySQL connection
mysql -u root -p -e "SELECT 1;"
```

#### Website Deployment Failed
```bash
# Check disk space
df -h /var/www

# Check nginx config
nginx -t

# Check worker permissions
ls -la /var/www/

# Test manual deployment
php worker.php deploy-test
```

#### Worker Service Not Starting
```bash
# Check service status
systemctl status vps-worker

# Check logs
journalctl -u vps-worker -f

# Restart service
systemctl restart vps-worker
```

### Common Error Messages

| Error | Solution |
|-------|----------|
| `Access denied for user 'root'@'localhost'` | Run MySQL fix script |
| `Unable to locate package php8.2-fpm` | Add PHP repository, use fallback |
| `Connection refused` | Check firewall/security groups |
| `SSL certificate generation failed` | Verify DNS propagation |
| `Worker not responding` | Check worker service status |
| `Could not open input file: artisan` | Fix permissions, ensure file exists |
| `Your requirements could not be resolved` | Fix composer.json versions |

---

## 🔒 Security

### Master Server Security
- [ ] Change default admin password
- [ ] Enable 2FA if available
- [ ] Configure firewall (UFW)
- [ ] Regular security updates
- [ ] Monitor failed login attempts
- [ ] Backup database daily

### Worker Server Security
- [ ] Disable root SSH login
- [ ] Use SSH keys only
- [ ] Change SSH port
- [ ] Install fail2ban
- [ ] Regular security updates
- [ ] Monitor file changes

### Website Security
- [ ] Auto SSL certificates
- [ ] Security headers
- [ ] Rate limiting
- [ ] WordPress hardening
- [ ] Regular updates
- [ ] Malware scanning

---

## ⚙️ Advanced Configuration

### Performance Optimization
```bash
# Nginx caching
fastcgi_cache_path /var/cache/nginx levels=1:2 keys_zone=FASTCGI:100m inactive=60m;

# PHP-FPM tuning
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
```

### High Availability Setup
```
Cloudflare Load Balancer
    ↓
Master Server (Primary)
    ↓
VPS 1 (Active) ←→ VPS 2 (Standby)
    ↓
Shared Database / File Storage
```

### Monitoring & Alerts
```bash
# Setup monitoring
crontab -e

# Add monitoring jobs
*/5 * * * * /opt/vps-manager/monitor.sh
0 1 * * * /opt/vps-manager/backup.sh
```

### API Integration
```bash
# API endpoints
curl -X POST https://master/api/worker/deploy \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your-api-key" \
  -d '{"domain": "test.com", "type": "html"}'
```

---

## 📊 Performance & Scaling

### Scaling Options
1. **Vertical**: Increase VPS resources
2. **Horizontal**: Add more worker nodes
3. **Load Balancing**: Distribute traffic
4. **CDN**: Cloudflare integration

### Resource Requirements
| Component | CPU | RAM | Disk | Bandwidth |
|-----------|-----|-----|------|-----------|
| Master Server | 2 cores | 4GB | 50GB | 1TB |
| Worker (Small) | 1 core | 2GB | 20GB | 500GB |
| Worker (Medium) | 2 cores | 4GB | 50GB | 1TB |
| Worker (Large) | 4 cores | 8GB | 100GB | 2TB |

### Monitoring Metrics
- Website uptime
- Response time
- Resource usage
- Error rates
- Bandwidth consumption

---

## 🛠️ Command Reference

### Master Server Commands
```bash
# Status check
vps-status

# View logs
tail -f /var/log/vps-manager/*.log

# Restart services
systemctl restart vps-worker nginx mysql

# Manual SSL
certbot --nginx -d domain.com

# Database backup
mysqldump -u root -p vps_manager > backup.sql

# Laravel commands
php artisan list
php artisan migrate
php artisan cache:clear
php artisan config:clear
```

### Worker Server Commands
```bash
# Worker status
systemctl status vps-worker

# View logs
tail -f /var/log/vps-worker/worker.log

# Test connection
php worker.php test-connection

# Manual deploy
php worker.php deploy --domain=test.com --type=html
```

### Website Management
```bash
# List websites
ls -la /var/www/

# Check nginx config
nginx -t

# Restart web server
systemctl reload nginx

# View access logs
tail -f /var/log/nginx/access.log
```

---

## 📞 Support

### Getting Help
1. **Check logs**: `/var/log/vps-manager/`, `/var/log/vps-worker/`
2. **Test commands**: `vps-status`, `nginx -t`
3. **Documentation**: See `docs/` folder
4. **Community**: GitHub Issues / Forum

### Useful Resources
- [Installation Guide](https://dangthanhson.com/vps/docs/install)
- [Video Tutorials](https://dangthanhson.com/vps/docs/videos)
- [API Documentation](https://dangthanhson.com/vps/docs/api)
- [Troubleshooting](https://dangthanhson.com/vps/docs/troubleshoot)

### Report Issues
```bash
# System information
lsb_release -a
mysql --version
nginx -v
php --version

# Create support bundle
tar -czf support-bundle.tar.gz /var/log/vps-* /etc/nginx/sites-available /opt/vps-manager/.env
```

---

## 📄 License & Credits

**VPS Manager** - Open Source VPS Management System
- License: MIT
- Author: VPS Manager Team
- Website: https://dangthanhson.com/vps
- GitHub: https://github.com/yourusername/vps-manager

**Built with:**
- Laravel Framework
- Vue.js Frontend
- MySQL Database
- Nginx Web Server
- PHP CLI Workers

---

## 🎉 Success!

Your VPS Manager is now ready! You can:
- ✅ Deploy unlimited websites
- ✅ Manage multiple VPS servers
- ✅ Auto SSL certificates
- ✅ Monitor performance
- ✅ Automated backups

**Next Steps:**
1. Deploy your first website
2. Add more worker servers
3. Configure monitoring
4. Setup automated backups
5. Scale as needed

**Need Help?** Check the troubleshooting section above or visit our support resources.

Happy hosting! 🚀