# Deployment Debug Guide

## Vấn đề hiện tại

1. **Subdomain không hiển thị dữ liệu mới sau khi save**
2. **Category links không truy cập được**
3. **Website không hiển thị dữ liệu**

## Nguyên nhân

### 1. Queue worker chưa chạy
Hệ thống sử dụng queue để deploy bất đồng bộ. Nếu queue worker không chạy, các deployment jobs sẽ không được xử lý.

### 2. Database chưa có jobs table
Migration cho jobs table cần được chạy.

### 3. Subdomain pages không được deploy khi save
Code trước đó không deploy subdomain pages khi save - **ĐÃ FIX**

## Cách fix

### Bước 1: Pull code mới

```bash
cd /home/user/vps
git pull origin claude/review-project-repo-011jZzfQCtKUoPt5rvsDBQm5
```

### Bước 2: Chạy migration

```bash
php artisan migrate --force
```

Nếu gặp lỗi database connection, kiểm tra MySQL:
```bash
# Kiểm tra MySQL đang chạy
ps aux | grep mysql

# Hoặc
service mysql status
```

### Bước 3: Khởi động queue worker

**Option 1: Chạy script tự động**
```bash
./start-queue.sh
```

**Option 2: Chạy thủ công**
```bash
# Chạy foreground (để test)
php artisan queue:work --verbose

# Chạy background
nohup php artisan queue:work --sleep=3 --tries=3 > storage/logs/queue.log 2>&1 &
```

**Option 3: Dùng systemd (recommended cho production)**
```bash
sudo cp vps-queue-worker.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable vps-queue-worker
sudo systemctl start vps-queue-worker
sudo systemctl status vps-queue-worker
```

### Bước 4: Test deployment thủ công

```bash
./deploy-test.php
```

Script này sẽ:
- List tất cả websites laravel1
- Deploy homepage cho main domains
- Deploy categories cho main domains
- Deploy pages cho subdomains
- Hiển thị kết quả deploy

### Bước 5: Kiểm tra jobs đang chờ

```bash
# Xem số lượng jobs trong queue
php artisan queue:monitor

# Hoặc check database trực tiếp
mysql -u vps_manager -p vps_manager -e "SELECT * FROM jobs ORDER BY id DESC LIMIT 10;"
```

### Bước 6: Kiểm tra logs

```bash
# Queue worker logs
tail -f storage/logs/queue-worker.log

# Laravel logs
tail -f storage/logs/laravel.log

# Worker API logs (trên VPS worker)
# Tùy thuộc vào cấu hình worker
```

## Kiểm tra từng vấn đề

### 1. Subdomain không hiển thị dữ liệu

**Nguyên nhân**: Code cũ không deploy subdomain page khi save

**Fix**: ĐÃ SỬA trong commit này - subdomain pages giờ sẽ tự động deploy khi save

**Test**:
1. Vào page edit của subdomain
2. Sửa nội dung
3. Save
4. Kiểm tra log: `tail -f storage/logs/laravel.log`
5. Mở link subdomain để xem

**Nếu vẫn không thấy**:
```bash
# Deploy thủ công
php artisan tinker
>>> $page = App\Models\Page::find(PAGE_ID);
>>> app(App\Services\DeploymentService::class)->deployPage($page);
```

### 2. Category không truy cập được

**Nguyên nhân**:
- Category chưa được deploy
- Hoặc queue worker chưa chạy

**Fix**:
1. Khởi động queue worker (xem Bước 3)
2. Save bất kỳ page nào → sẽ trigger deploy category
3. Hoặc deploy thủ công:

```bash
./deploy-test.php
```

**Kiểm tra URL structure**:
- Main domain: `https://domain.com/`
- Category: `https://domain.com/category-slug/`
- Subdomain: `https://sub.domain.com/`

### 3. Homepage không hiển thị dữ liệu

**Nguyên nhân**: Homepage chưa được deploy với dữ liệu mới

**Fix**:
1. Đảm bảo queue worker đang chạy
2. Save bất kỳ page nào trong main domain
3. Queue sẽ tự động deploy lại homepage với data mới
4. Hoặc deploy thủ công:

```bash
php artisan tinker
>>> $website = App\Models\Website::find(WEBSITE_ID);
>>> app(App\Services\DeploymentService::class)->deployLaravel1Homepage($website);
```

## Troubleshooting

### Queue worker keep stopping

Dùng systemd hoặc supervisor để tự động restart:
```bash
sudo systemctl enable vps-queue-worker
sudo systemctl start vps-queue-worker
```

### Jobs failed

Xem failed jobs:
```bash
php artisan queue:failed

# Retry all
php artisan queue:retry all
```

### Deployment API không response

Kiểm tra worker server:
```bash
# Trên worker server
ps aux | grep worker
curl http://localhost:8080/health
```

### Category deployed nhưng trả về 404

Kiểm tra nginx/apache config:
```bash
# Category cần rewrite rules
# Ví dụ nginx:
location / {
    try_files $uri $uri/ /index.html;
}
```

## Monitoring

### Check queue worker đang chạy

```bash
ps aux | grep "queue:work"
pgrep -f "queue:work"
```

### Check số jobs đang chờ

```bash
mysql -u vps_manager -p vps_manager -e "SELECT COUNT(*) as pending FROM jobs;"
```

### Auto-restart queue worker khi crash

Dùng systemd service hoặc thêm vào crontab:
```bash
* * * * * cd /home/user/vps && ./start-queue.sh >> /dev/null 2>&1
```

## Summary

**Để hệ thống hoạt động đúng**:
1. ✅ Pull code mới (đã fix subdomain deployment)
2. ✅ Chạy migration (tạo jobs table)
3. ✅ Khởi động queue worker
4. ✅ Test deployment với `./deploy-test.php`
5. ✅ Setup systemd để queue worker tự động chạy
