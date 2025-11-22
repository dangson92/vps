# Queue Worker Setup

## Chạy Migration

Trước tiên, chạy migration để tạo jobs table:

```bash
cd /home/user/vps
php artisan migrate --force
```

## Option 1: Systemd Service (Khuyến nghị)

### Setup

```bash
# Copy service file
sudo cp vps-queue-worker.service /etc/systemd/system/

# Reload systemd
sudo systemctl daemon-reload

# Enable service (chạy tự động khi boot)
sudo systemctl enable vps-queue-worker

# Start service
sudo systemctl start vps-queue-worker

# Kiểm tra status
sudo systemctl status vps-queue-worker
```

### Quản lý

```bash
# Xem logs realtime
sudo journalctl -u vps-queue-worker -f

# Restart service
sudo systemctl restart vps-queue-worker

# Stop service
sudo systemctl stop vps-queue-worker
```

### Restart sau khi update code

Sau khi pull code mới, cần restart worker:

```bash
sudo systemctl restart vps-queue-worker
```

## Option 2: Supervisor

### Cài đặt Supervisor

```bash
sudo apt-get install supervisor
```

### Setup

```bash
# Copy config
sudo cp vps-queue-worker.conf /etc/supervisor/conf.d/

# Reload supervisor
sudo supervisorctl reread
sudo supervisorctl update

# Start worker
sudo supervisorctl start vps-queue-worker:*
```

### Quản lý

```bash
# Xem status
sudo supervisorctl status

# Restart
sudo supervisorctl restart vps-queue-worker:*

# Stop
sudo supervisorctl stop vps-queue-worker:*

# Xem logs
tail -f /home/user/vps/storage/logs/queue-worker.log
```

## Kiểm tra Queue đang hoạt động

Sau khi save page, kiểm tra:

```bash
# Xem jobs trong queue
php artisan queue:monitor

# Hoặc check database
mysql -u vps_manager -p vps_manager -e "SELECT * FROM jobs ORDER BY id DESC LIMIT 10;"
```

## Troubleshooting

### Worker không chạy

```bash
# Systemd
sudo systemctl status vps-queue-worker
sudo journalctl -u vps-queue-worker -n 50

# Supervisor
sudo supervisorctl status
tail -f /home/user/vps/storage/logs/queue-worker.log
```

### Jobs không được xử lý

```bash
# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```
