# Fix Homepage & Category Data Display

## Vấn đề

1. ✅ **Subdomain** - Đã OK
2. ❌ **Category chưa sắp xếp** theo thời gian cập nhật/tạo mới
3. ❌ **Homepage không hiển thị** dữ liệu ở các section
4. ❌ **Template CSS/JS 404 error** - style.css và script.js không load

## Nguyên nhân

### Vấn đề 2 & 3: Data không hiển thị
Code đã có `orderBy('updated_at', 'desc')` rồi, nhưng:
- **Category đã được deploy TRƯỚC KHI code được cập nhật**
- **Homepage đã được deploy TRƯỚC KHI code được cập nhật**

→ Cần **redeploy lại** tất cả để áp dụng code mới!

### Vấn đề 4: Template assets 404
Template CSS/JS chỉ được deploy khi **deploy website lần đầu**, nhưng không được deploy khi **redeploy homepage qua queue**.

→ Cần **deploy template assets** một lần!

## Giải pháp

### Bước 0: Fix template assets 404 (làm trước tiên!)

```bash
./fix-template-assets.sh
```

Script này sẽ:
- Deploy tất cả template CSS/JS files (home-1, listing-1, hotel-detail-1)
- Kiểm tra files đã được deploy chưa
- Hiển thị path của các files

**Output mong đợi:**
```
✅ home-1 folder exists
-rw-r--r-- 1 www-data www-data 2.1K style.css
-rw-r--r-- 1 www-data www-data 2.4K script.js

✅ listing-1 folder exists
-rw-r--r-- 1 www-data www-data 1.8K style.css
-rw-r--r-- 1 www-data www-data 1.5K script.js

✅ hotel-detail-1 folder exists
-rw-r--r-- 1 www-data www-data 3.2K style.css
-rw-r--r-- 1 www-data www-data 4.1K script.js
```

**Sau khi chạy xong, reload trang homepage để thấy CSS/JS đã load!**

### Bước 1: Kiểm tra homepage hiện tại

```bash
./check-deployed-homepage.sh
```

Script này sẽ kiểm tra:
- Homepage file có tồn tại không
- Có `page-data` script tag không
- Có dữ liệu categories, featured, newest không
- Các section có tồn tại không

**Nếu thấy:**
- ❌ `{{PAGE_DATA_SCRIPT}}` placeholder → Homepage chưa được render đúng
- ❌ page-data script tag NOT found → Không có dữ liệu

→ Cần redeploy

### Bước 2: Redeploy tất cả

```bash
./redeploy-all.php
```

Script này sẽ:
- Queue deployment cho tất cả homepage
- Queue deployment cho tất cả categories
- Hiển thị số lượng jobs đã tạo

**Output mong đợi:**
```
Website: yourdomain.com
  ✓ Homepage deployment queued
  ✓ Category 'Category 1' deployment queued
  ✓ Category 'Category 2' deployment queued

=== Queued for Deployment ===
Homepages: 1
Categories: 3

Current pending jobs in queue: 4
```

### Bước 3: Đợi queue worker xử lý

Theo dõi queue worker:

```bash
# Xem logs realtime
sudo journalctl -u vps-queue-worker -f

# Hoặc
tail -f /home/user/vps/storage/logs/queue-worker.log
```

**Chờ đến khi thấy:**
```
Processing: App\Jobs\DeployLaravel1Homepage
Processed:  App\Jobs\DeployLaravel1Homepage
Processing: App\Jobs\DeployLaravel1CategoryPage
Processed:  App\Jobs\DeployLaravel1CategoryPage
```

Mất khoảng **30 giây - 1 phút** tùy số lượng categories.

### Bước 4: Kiểm tra lại

```bash
./check-deployed-homepage.sh
```

**Kết quả mong đợi:**
```
✅ Homepage file exists
✅ page-data script tag found

=== Page Data Content ===
{
  "categories": [...],
  "featured": [...],
  "newest": [...]
}

✅ Categories section exists
✅ Featured section exists
✅ Newest section exists
```

### Bước 5: Test trên browser

1. **Homepage**: `https://yourdomain.com`
   - Phải thấy 3 sections:
     - Browse by Category (với ảnh từ page đầu tiên của mỗi category)
     - Featured Properties (6 pages)
     - Newest Properties (6 pages mới nhất)

2. **Category**: `https://yourdomain.com/category-slug`
   - Pages phải được sắp xếp từ **mới nhất → cũ nhất**
   - Check updated_at date

3. **Subdomain**: `https://sub.yourdomain.com`
   - Đã OK rồi

## Troubleshooting

### Homepage vẫn không có dữ liệu

**Nguyên nhân có thể:**

1. **Không có pages trong folders**
   ```bash
   mysql -u vps_manager -pa22f3fda13ee00562544c950e33aceb2 vps_manager -e "
   SELECT f.name as folder, COUNT(fp.page_id) as pages
   FROM folders f
   LEFT JOIN folder_page fp ON f.id = fp.folder_id
   GROUP BY f.id, f.name;"
   ```

   **Fix**: Gắn pages vào folders trong admin panel

2. **Pages không có template_data**
   ```bash
   mysql -u vps_manager -pa22f3fda13ee00562544c950e33aceb2 vps_manager -e "
   SELECT id, title, template_data IS NULL as no_data
   FROM pages
   LIMIT 10;"
   ```

   **Fix**: Edit pages và nhập dữ liệu template (gallery, title, location...)

3. **Queue worker không chạy**
   ```bash
   sudo systemctl status vps-queue-worker
   pgrep -af "queue:work"
   ```

   **Fix**:
   ```bash
   sudo systemctl restart vps-queue-worker
   ```

### Category vẫn chưa sắp xếp đúng

1. Check code có orderBy chưa:
   ```bash
   grep -n "orderBy.*updated_at" /home/user/vps/app/Services/DeploymentService.php
   ```

   Phải thấy dòng:
   ```
   196:        $pages = $folder->pages()->orderBy('updated_at', 'desc')->get();
   ```

2. Redeploy lại category:
   ```bash
   ./redeploy-all.php
   ```

3. Đợi queue worker xử lý

4. Clear browser cache và reload

### Template assets (CSS/JS) không load

Categories và homepage cần template assets:

```bash
# Check files exist
ls -la /var/www/yourdomain.com/templates/

# Redeploy template assets
php artisan tinker
>>> $website = App\Models\Website::where('domain', 'yourdomain.com')->first();
>>> app(App\Services\DeploymentService::class)->deployTemplateAssets($website, 'home-1');
>>> app(App\Services\DeploymentService::class)->deployTemplateAssets($website, 'listing-1');
```

## Debug Scripts

Các scripts đã tạo:

1. **check-deployed-homepage.sh** - Kiểm tra homepage đã deploy
2. **redeploy-all.php** - Redeploy tất cả homepage và categories
3. **debug-homepage-data.php** - Debug dữ liệu homepage (cần MySQL running)
4. **deploy-test.php** - Test deployment thủ công
5. **start-queue.sh** - Start queue worker

## Checklist

- [ ] Queue worker đang chạy (`sudo systemctl status vps-queue-worker`)
- [ ] Migration đã chạy (`jobs` table tồn tại)
- [ ] Folders có pages (`SELECT COUNT(*) FROM folder_page`)
- [ ] Pages có template_data (`SELECT COUNT(*) FROM pages WHERE template_data IS NOT NULL`)
- [ ] Redeploy tất cả (`./redeploy-all.php`)
- [ ] Đợi queue xử lý xong
- [ ] Check homepage (`./check-deployed-homepage.sh`)
- [ ] Test trên browser
- [ ] Clear browser cache nếu cần

## Lệnh nhanh

```bash
# Full redeploy
./redeploy-all.php && sleep 10 && ./check-deployed-homepage.sh

# Check queue status
sudo journalctl -u vps-queue-worker -n 50 --no-pager

# Check pending jobs
mysql -u vps_manager -pa22f3fda13ee00562544c950e33aceb2 vps_manager -e "SELECT COUNT(*) as pending FROM jobs;"

# Force process queue now (if worker stuck)
php artisan queue:work --once --tries=3

# Restart queue worker
sudo systemctl restart vps-queue-worker
```
