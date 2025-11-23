# Shared Template Components

This directory contains shared HTML components used across all templates.

## Files

- **header.html** - Common header with logo, navigation, and auth buttons
- **footer.html** - Common footer with links and copyright

## How to Use

### 1. Update Shared Components

Edit the files in this directory to make changes to header/footer:

```bash
# Edit header
nano /home/user/vps/public/templates/_shared/header.html

# Edit footer
nano /home/user/vps/public/templates/_shared/footer.html
```

### 2. Sync to All Templates

Run the sync script to apply changes to all templates:

```bash
cd /home/user/vps
chmod +x sync-template-components.php
./sync-template-components.php
```

This will update header/footer in:
- home-1/index.html
- listing-1/index.html
- hotel-detail-1/index.html

### 3. Deploy to Server

After syncing, deploy the updated templates:

```bash
# Deploy all templates for a domain
./redeploy-template-assets.php home-1
./redeploy-template-assets.php listing-1
./redeploy-template-assets.php hotel-detail-1

# Or deploy to specific website
php artisan tinker
$website = App\Models\Website::where('domain', 'timnhakhoa.com')->first();
$service = app(App\Services\DeploymentService::class);
$service->deployTemplateAssets($website, 'hotel-detail-1');
```

## Notes

- The sync script uses regex to find and replace `<header>...</header>` and `<footer>...</footer>` blocks
- Make sure to keep the opening `<header>` and `<footer>` tags properly formatted
- Always test changes on a staging environment first
