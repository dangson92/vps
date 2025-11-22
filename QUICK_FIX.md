# Quick Fix - Template Assets & Data Display

## Vấn đề với scripts cũ

Scripts cũ cần MySQL connection, nhưng MySQL không chạy hoặc không kết nối được.

## Giải pháp: Script đơn giản không cần database

### Bước 1: Deploy Template Assets

**Thay thế domain của bạn:**

```bash
# Cú pháp: php deploy-assets-simple.php <domain> [vps_id] [worker_key]
php deploy-assets-simple.php yourdomain.com
```

Hoặc đầy đủ:
```bash
php deploy-assets-simple.php dangthanhson.com 1 112435988
```

Script này sẽ:
- Deploy CSS/JS files cho home-1, listing-1, hotel-detail-1
- Không cần database connection
- Hiển thị kết quả deploy

### Bước 2: Redeploy Homepage & Categories qua Admin Panel

Vì scripts cần database, cách nhanh nhất là:

**Option A: Qua Admin Panel**
1. Vào admin panel
2. Edit bất kỳ page nào
3. Save lại
4. Queue worker sẽ tự động redeploy homepage và categories

**Option B: Restart Queue Worker (force redeploy tất cả)**
```bash
# Stop queue worker
sudo systemctl stop vps-queue-worker

# Start queue worker (sẽ xử lý pending jobs)
sudo systemctl start vps-queue-worker
```

**Option C: Chạy trực tiếp với Laravel Tinker**
```bash
php artisan tinker
```

Trong tinker:
```php
// Get your main domain website
$website = App\Models\Website::where('domain', 'dangthanhson.com')->first();

// Deploy homepage
App\Jobs\DeployLaravel1Homepage::dispatch($website->id);

// Deploy all categories
$folders = App\Models\Folder::where('website_id', $website->id)->get();
foreach ($folders as $folder) {
    App\Jobs\DeployLaravel1CategoryPage::dispatch($folder->id);
}

// Check queued jobs
DB::table('jobs')->count();

exit
```

Sau đó đợi queue worker xử lý:
```bash
sudo journalctl -u vps-queue-worker -f
```

### Bước 3: Alternative - Deploy thủ công không cần queue

Nếu queue worker không chạy hoặc có vấn đề, deploy trực tiếp:

```bash
php artisan tinker
```

```php
$website = App\Models\Website::where('domain', 'dangthanhson.com')->first();
$service = app(App\Services\DeploymentService::class);

// Deploy homepage
$service->deployLaravel1Homepage($website);

// Deploy categories
$folders = App\Models\Folder::where('website_id', $website->id)->get();
foreach ($folders as $folder) {
    $service->deployLaravel1CategoryPage($folder);
}

exit
```

## Checklist

- [ ] Deploy template assets: `php deploy-assets-simple.php yourdomain.com`
- [ ] Check CSS/JS load: Reload homepage, check browser console
- [ ] Deploy homepage & categories (chọn 1 trong 3 options trên)
- [ ] Đợi queue xử lý (nếu dùng queue)
- [ ] Test homepage: Categories, Featured, Newest sections
- [ ] Test category: Pages sắp xếp theo mới nhất

## Troubleshooting

### Script báo "cannot execute: required file not found"

Chạy với `bash` trực tiếp:
```bash
bash fix-template-assets.sh
```

Hoặc dùng script mới không cần bash:
```bash
php deploy-assets-simple.php yourdomain.com
```

### MySQL Connection Refused

Scripts cũ cần MySQL. Có 2 cách:

1. **Start MySQL** (nếu đang dừng):
```bash
sudo service mysql start
# hoặc
sudo systemctl start mysql
```

2. **Dùng scripts mới** không cần MySQL:
```bash
php deploy-assets-simple.php yourdomain.com
```

Và deploy qua tinker như hướng dẫn trên.

### Queue worker không chạy

```bash
# Check status
sudo systemctl status vps-queue-worker

# Start nếu stopped
sudo systemctl start vps-queue-worker

# Restart
sudo systemctl restart vps-queue-worker
```

### Không có systemd (shared hosting, Docker, etc)

Chạy queue worker manual:
```bash
# Foreground
php artisan queue:work --verbose

# Background
nohup php artisan queue:work > storage/logs/queue.log 2>&1 &
```

## Tóm tắt lệnh nhanh

```bash
# 1. Deploy template assets
php deploy-assets-simple.php dangthanhson.com

# 2. Deploy homepage & categories qua tinker
php artisan tinker
$w = App\Models\Website::where('domain', 'dangthanhson.com')->first();
app(App\Services\DeploymentService::class)->deployLaravel1Homepage($w);
$folders = App\Models\Folder::where('website_id', $w->id)->get();
foreach ($folders as $f) app(App\Services\DeploymentService::class)->deployLaravel1CategoryPage($f);
exit

# 3. Test
curl -I https://dangthanhson.com/templates/home-1/style.css
```

Xong! Homepage và categories đã có CSS/JS và data mới nhất.
