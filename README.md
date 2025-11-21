# VPS Manager - Hướng Dẫn Cài Đặt

## Yêu Cầu Hệ Thống
- **Master Server**: Ubuntu 20.04+ / Debian 10+ (2GB RAM, 20GB disk)
- **Worker Server**: Ubuntu/Debian với ít nhất 1GB RAM
- **Tên miền** với quyền quản lý DNS (Khuyên dùng Cloudflare)
- **Quyền root/sudo** trên các server

## Cài Đặt Nhanh (5 phút)

### 1. Cài Đặt Master Server
```bash
# Kết nối đến master server
ssh root@your-master-server

# Cập nhật hệ thống
apt update && apt upgrade -y

# Cài đặt các gói cần thiết
apt install -y curl wget git unzip

# Tải và chạy script cài đặt
wget https://dangthanhson.com/vps/install.sh
chmod +x install.sh
sudo ./install.sh
```

### 2. Cài Đặt Worker Server
```bash
# Kết nối đến worker VPS
ssh root@your-worker-vps

# Cập nhật hệ thống
apt update && apt upgrade -y

# Tải script cài đặt worker
wget https://dangthanhson.com/vps/worker-setup.sh
chmod +x worker-setup.sh
sudo ./worker-setup.sh
```

### 3. Truy Cập Dashboard
- URL: `https://your-domain.com`
- Lấy worker key từ master dashboard
- Thêm worker trong master dashboard → VPS Servers → Add New

## Xử Lý Lỗi Thường Gặp

### Lỗi PHP Installation Failed
```bash
# Fix repository PHP
sudo apt install software-properties-common -y
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Cài PHP với fallback
sudo apt install php8.2-fpm php8.2-{mysql,curl,gd,mbstring,xml,zip,bcmath} || \
sudo apt install php8.1-fpm php8.1-{mysql,curl,gd,mbstring,xml,zip,bcmath}
```

### Lỗi MySQL Access Denied (Amazon VPS)
```bash
# Tải script fix MySQL
wget https://dangthanhson.com/vps/fix-mysql-password.sh
chmod +x fix-mysql-password.sh
sudo ./fix-mysql-password.sh

# Tiếp tục cài đặt
cd /opt/vps-manager
sudo ./install.sh
```

### Lỗi Composer Dependencies
```bash
# Fix composer.json và cài lại
cd /opt/vps-manager
rm -rf vendor composer.lock
composer install --no-dev --optimize-autoloader
```

### Lỗi Missing Artisan File
```bash
# Đảm bảo artisan tồn tại và có quyền
chmod +x artisan
php artisan key:generate
php artisan config:cache
```

## Triển Khai Website Đầu Tiên
1. Đăng nhập vào master dashboard
2. Click "Websites" → "Add Website"
3. Chọn HTML hoặc WordPress
4. Nhập domain và chọn VPS
5. Click "Deploy" (chờ 30-60 giây)
6. Truy cập website!