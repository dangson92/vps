<?php

namespace App\Services;

use App\Models\Website;
use App\Models\Page;
use App\Models\Folder;
use App\Models\VpsServer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeploymentService
{
    public function deploy(Website $website): void
    {
        $vps = $website->vpsServer;
        
        if (!$vps->isActive()) {
            throw new \Exception('VPS server is not active');
        }

        // Send deployment command to worker
        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'X-Worker-Key' => $vps->worker_key,
                    'Content-Type' => 'application/json',
                ])
                ->post("http://{$vps->ip_address}:8080/api/deploy", [
                    'website_id' => $website->id,
                    'domain' => $website->domain,
                    'type' => $website->type,
                    'document_root' => $website->getDocumentRoot(),
                    'wordpress_config' => $website->wordpress_config,
                    'nginx_config' => $this->generateNginxConfig($website),
                ]);
        } catch (ConnectionException $e) {
            $response = Http::timeout(60)
                ->withHeaders([
                    'X-Worker-Key' => $vps->worker_key,
                    'Content-Type' => 'application/json',
                ])
                ->post("http://127.0.0.1:8080/api/deploy", [
                    'website_id' => $website->id,
                    'domain' => $website->domain,
                    'type' => $website->type,
                    'document_root' => $website->getDocumentRoot(),
                    'wordpress_config' => $website->wordpress_config,
                    'nginx_config' => $this->generateNginxConfig($website),
                ]);
        }

        if (!$response->successful()) {
            throw new \Exception('Deployment failed: ' . $response->body());
        }

        Log::info("Website base deployed successfully", ['website_id' => $website->id]);

        // Deploy laravel1 homepage and category pages
        if ($website->type === 'laravel1') {
            $this->deployTemplateAssets($website, 'home-1');
            $this->deployTemplateAssets($website, 'listing-1');
            $this->deployTemplateAssets($website, 'hotel-detail-1');
            $this->deployLaravel1Homepage($website);
            $this->deployLaravel1AllCategories($website);
        }
    }

    public function deployLaravel1AllCategories(Website $website): void
    {
        $folders = Folder::where('website_id', $website->id)->get();
        foreach ($folders as $folder) {
            $this->deployLaravel1CategoryPage($folder);
        }
    }

    public function deployLaravel1Homepage(Website $website): void
    {
        $vps = $website->vpsServer;

        // Generate homepage HTML
        $folders = Folder::where('website_id', $website->id)->whereNull('parent_id')->get();
        $categoriesData = $folders->map(function ($folder) use ($website) {
            $pageCount = $folder->pages()->count();
            $firstPage = $folder->pages()->first();
            $firstPageData = $firstPage ? ($firstPage->template_data ?? []) : [];
            $gallery = $firstPageData['gallery'] ?? [];

            // Category URLs should always point to main domain, not subdomain
            $protocol = $website->ssl_enabled ? 'https://' : 'http://';
            $categoryUrl = $protocol . $website->domain . '/' . $folder->slug;

            return [
                'name' => $folder->name,
                'url' => $categoryUrl,
                'count' => $pageCount,
                'image' => $gallery[0] ?? 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400',
            ];
        })->toArray();

        // Get featured pages from all folders
        $featuredPages = [];
        foreach ($folders as $folder) {
            $folderPages = $folder->pages()->limit(3)->get();
            foreach ($folderPages as $page) {
                $featuredPages[] = $page;
                if (count($featuredPages) >= 8) break 2;
            }
        }

        $featuredData = collect($featuredPages)->map(function ($page) use ($website) {
            $data = $page->template_data ?? [];
            $gallery = $data['gallery'] ?? [];

            // Generate proper URL for page
            $url = $this->generatePageUrl($page, $website);

            return [
                'title' => $data['title'] ?? $page->title ?? 'Untitled',
                'image' => $gallery[0] ?? '',
                'location_text' => $data['location_text'] ?? $data['location'] ?? '',
                'url' => $url,
            ];
        })->toArray();

        // Get newest pages (most recently updated)
        $newestPages = [];
        foreach ($folders as $folder) {
            $folderPages = $folder->pages()->orderBy('updated_at', 'desc')->limit(3)->get();
            foreach ($folderPages as $page) {
                $newestPages[] = $page;
            }
        }

        // Sort by updated_at and take top 8
        $newestPages = collect($newestPages)->sortByDesc('updated_at')->take(8);
        $newestData = $newestPages->map(function ($page) use ($website) {
            $data = $page->template_data ?? [];
            $gallery = $data['gallery'] ?? [];

            // Generate proper URL for page
            $url = $this->generatePageUrl($page, $website);

            return [
                'title' => $data['title'] ?? $page->title ?? 'Untitled',
                'image' => $gallery[0] ?? '',
                'location_text' => $data['location_text'] ?? $data['location'] ?? '',
                'url' => $url,
            ];
        })->values()->toArray();

        $templatePath = public_path('templates/home-1/index.html');
        $html = file_exists($templatePath) ? file_get_contents($templatePath) : '<h1>Template not found</h1>';

        $html = str_replace('{{TITLE}}', e($website->domain), $html);
        $html = str_replace('{{DESCRIPTION}}', 'Find your perfect stay at ' . e($website->domain), $html);
        $html = str_replace('{{OG_IMAGE}}', $featuredData[0]['image'] ?? '', $html);
        $html = str_replace('{{OG_URL}}', 'https://' . $website->domain, $html);

        $dataScript = '<script type="application/json" id="page-data">' . json_encode([
            'categories' => $categoriesData,
            'featured' => $featuredData,
            'newest' => $newestData,
        ]) . '</script>';
        $html = str_replace('{{PAGE_DATA_SCRIPT}}', $dataScript, $html);

        // Deploy the homepage
        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'X-Worker-Key' => $vps->worker_key,
                    'Content-Type' => 'application/json',
                ])
                ->post("http://{$vps->ip_address}:8080/api/deploy-page", [
                    'website_id' => $website->id,
                    'page_path' => '/',
                    'filename' => 'index.html',
                    'content' => $html,
                    'document_root' => $website->getDocumentRoot(),
                ]);
        } catch (ConnectionException $e) {
            Http::timeout(60)
                ->withHeaders([
                    'X-Worker-Key' => $vps->worker_key,
                    'Content-Type' => 'application/json',
                ])
                ->post("http://127.0.0.1:8080/api/deploy-page", [
                    'website_id' => $website->id,
                    'page_path' => '/',
                    'filename' => 'index.html',
                    'content' => $html,
                    'document_root' => $website->getDocumentRoot(),
                ]);
        }

        Log::info("Laravel1 homepage deployed", ['website_id' => $website->id]);
    }

    public function deployLaravel1CategoryPage(Folder $folder): void
    {
        $website = $folder->website;
        $vps = $website->vpsServer;

        if (!$vps || !$vps->isActive()) {
            return;
        }

        $pages = $folder->pages()->orderBy('updated_at', 'desc')->get();
        $pagesData = $pages->map(function ($page) use ($website) {
            $data = $page->template_data ?? [];
            $gallery = $data['gallery'] ?? [];

            // Generate proper URL for page
            $url = $this->generatePageUrl($page, $website);

            return [
                'title' => $data['title'] ?? $page->title ?? 'Untitled',
                'description' => $data['about1'] ?? '',
                'image' => $gallery[0] ?? '',
                'location_text' => $data['location_text'] ?? $data['location'] ?? '',
                'url' => $url,
                'amenities' => $data['amenities'] ?? [],
            ];
        })->toArray();

        $templatePath = public_path('templates/listing-1/index.html');
        $html = file_exists($templatePath) ? file_get_contents($templatePath) : '<h1>Template not found</h1>';

        $folderName = $folder->name ?? 'Category';
        $folderDesc = $folder->description ?? 'Browse all properties in this category';

        // Get full path including parent folders (e.g., /vietnam/halong)
        $folderPath = $folder->getPath();

        $html = str_replace('{{TITLE}}', e($folderName), $html);
        $html = str_replace('{{DESCRIPTION}}', e($folderDesc), $html);
        $html = str_replace('{{OG_IMAGE}}', $pagesData[0]['image'] ?? '', $html);
        $html = str_replace('{{OG_URL}}', 'https://' . $website->domain . $folderPath, $html);

        $dataScript = '<script type="application/json" id="page-data">' . json_encode(['pages' => $pagesData]) . '</script>';
        $html = str_replace('{{PAGE_DATA_SCRIPT}}', $dataScript, $html);

        try {
            Http::timeout(60)
                ->withHeaders([
                    'X-Worker-Key' => $vps->worker_key,
                    'Content-Type' => 'application/json',
                ])
                ->post("http://{$vps->ip_address}:8080/api/deploy-page", [
                    'website_id' => $website->id,
                    'page_path' => $folderPath,
                    'filename' => 'index.html',
                    'content' => $html,
                    'document_root' => $website->getDocumentRoot(),
                ]);
        } catch (ConnectionException $e) {
            Http::timeout(60)
                ->withHeaders([
                    'X-Worker-Key' => $vps->worker_key,
                    'Content-Type' => 'application/json',
                ])
                ->post("http://127.0.0.1:8080/api/deploy-page", [
                    'website_id' => $website->id,
                    'page_path' => $folderPath,
                    'filename' => 'index.html',
                    'content' => $html,
                    'document_root' => $website->getDocumentRoot(),
                ]);
        }

        Log::info("Laravel1 category page deployed", ['folder_id' => $folder->id]);
    }

    public function deployPage(Page $page, ?string $oldPath = null, ?string $oldFilename = null): void
    {
        $website = $page->website;
        $vps = $website->vpsServer;

        if (!$vps->isActive()) {
            throw new \Exception('VPS server is not active');
        }

        // Render page content with template data
        $content = $this->renderPageContent($page);

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'X-Worker-Key' => $vps->worker_key,
                    'Content-Type' => 'application/json',
                ])
                ->post("http://{$vps->ip_address}:8080/api/deploy-page", [
                    'website_id' => $website->id,
                    'page_path' => $page->path,
                    'filename' => $page->filename,
                    'content' => $content,
                    'document_root' => $website->getDocumentRoot(),
                    'old_path' => $oldPath,
                    'old_filename' => $oldFilename,
                ]);
        } catch (ConnectionException $e) {
            $response = Http::timeout(60)
                ->withHeaders([
                    'X-Worker-Key' => $vps->worker_key,
                    'Content-Type' => 'application/json',
                ])
                ->post("http://127.0.0.1:8080/api/deploy-page", [
                    'website_id' => $website->id,
                    'page_path' => $page->path,
                    'filename' => $page->filename,
                    'content' => $content,
                    'document_root' => $website->getDocumentRoot(),
                    'old_path' => $oldPath,
                    'old_filename' => $oldFilename,
                ]);
        }

        if (!$response->successful()) {
            throw new \Exception('Page deployment failed: ' . $response->body());
        }

        Log::info("Page deployed successfully", ['page_id' => $page->id]);
    }

    private function renderPageContent(Page $page): string
    {
        $html = $page->content;
        $data = $page->template_data ?? [];

        // If no template data, return raw content
        if (empty($data)) {
            return $html;
        }

        // Add primary folder info for breadcrumb
        // Use primary folder if set, otherwise use first folder
        $folder = null;
        if ($page->primary_folder_id) {
            $folder = $page->primaryFolder;
        } else {
            $folder = $page->folders()->first();
        }

        if ($folder) {
            $data['primary_folder_name'] = $folder->name;
            $data['primary_folder_path'] = $folder->getPath();

            // Generate breadcrumb_items from folder hierarchy
            $breadcrumbItems = ['Home'];

            // Get all parent folders in order
            $folderHierarchy = [];
            $currentFolder = $folder;
            while ($currentFolder) {
                array_unshift($folderHierarchy, $currentFolder);
                $currentFolder = $currentFolder->parent;
            }

            // Add folder names to breadcrumb
            foreach ($folderHierarchy as $f) {
                $breadcrumbItems[] = $f->name;
            }

            // Add page title
            $breadcrumbItems[] = $data['title'] ?? $page->title ?? 'Untitled';

            // Update breadcrumb_items
            $data['breadcrumb_items'] = $breadcrumbItems;
        }

        // Add main domain URL for breadcrumb
        $website = $page->website;
        $domainParts = explode('.', $website->domain);
        $mainDomain = count($domainParts) > 2 ? implode('.', array_slice($domainParts, -2)) : $website->domain;
        $protocol = $website->ssl_enabled ? 'https://' : 'http://';
        $data['main_domain_url'] = $protocol . $mainDomain;

        // Replace placeholders
        $title = $data['title'] ?? $page->title ?? 'Untitled';
        $description = $data['about1'] ?? $page->meta_description ?? '';
        $gallery = $data['gallery'] ?? [];

        $html = str_replace('{{TITLE}}', e($title), $html);
        $html = str_replace('{{DESCRIPTION}}', e($description), $html);
        $html = str_replace('{{OG_IMAGE}}', $gallery[0] ?? '', $html);
        $html = str_replace('{{OG_URL}}', 'https://' . $website->domain . $page->path, $html);

        // Inject page data script
        $dataScript = '<script type="application/json" id="page-data">' . json_encode($data) . '</script>';

        // Try to replace placeholder first
        if (strpos($html, '{{PAGE_DATA_SCRIPT}}') !== false) {
            $html = str_replace('{{PAGE_DATA_SCRIPT}}', $dataScript, $html);
        } else {
            // If no placeholder, replace hardcoded script tag
            $html = preg_replace(
                '/<script[^>]*id=["\']page-data["\'][^>]*>.*?<\/script>/s',
                $dataScript,
                $html
            );
        }

        return $html;
    }

    public function deployTemplateAssets(Website $website, string $templateName): void
    {
        $vps = $website->vpsServer;
        if (!$vps || !$vps->isActive()) return;

        $templateDir = public_path("templates/{$templateName}");
        $files = ['style.css', 'script.js'];

        foreach ($files as $file) {
            $filePath = "{$templateDir}/{$file}";
            if (!file_exists($filePath)) continue;

            $content = file_get_contents($filePath);
            try {
                Http::timeout(30)
                    ->withHeaders(['X-Worker-Key' => $vps->worker_key, 'Content-Type' => 'application/json'])
                    ->post("http://{$vps->ip_address}:8080/api/deploy-page", [
                        'website_id' => $website->id,
                        'page_path' => "/templates/{$templateName}",
                        'filename' => $file,
                        'content' => $content,
                        'document_root' => $website->getDocumentRoot(),
                    ]);
            } catch (ConnectionException $e) {
                Http::timeout(30)
                    ->withHeaders(['X-Worker-Key' => $vps->worker_key, 'Content-Type' => 'application/json'])
                    ->post("http://127.0.0.1:8080/api/deploy-page", [
                        'website_id' => $website->id,
                        'page_path' => "/templates/{$templateName}",
                        'filename' => $file,
                        'content' => $content,
                        'document_root' => $website->getDocumentRoot(),
                    ]);
            }
        }
    }

    public function removePage(Page $page): void
    {
        $website = $page->website;
        $vps = $website->vpsServer;

        if (!$vps->isActive()) {
            throw new \Exception('VPS server is not active');
        }

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'X-Worker-Key' => $vps->worker_key,
                    'Content-Type' => 'application/json',
                ])
                ->post("http://{$vps->ip_address}:8080/api/remove-page", [
                    'website_id' => $website->id,
                    'page_path' => $page->path,
                    'filename' => (ltrim($page->path, '/') ? rtrim(ltrim($page->path, '/'), '/') . '/' : '') . $page->filename,
                    'document_root' => $website->getDocumentRoot(),
                ]);
        } catch (ConnectionException $e) {
            $response = Http::timeout(60)
                ->withHeaders([
                    'X-Worker-Key' => $vps->worker_key,
                    'Content-Type' => 'application/json',
                ])
                ->post("http://127.0.0.1:8080/api/remove-page", [
                    'website_id' => $website->id,
                    'page_path' => $page->path,
                    'filename' => (ltrim($page->path, '/') ? rtrim(ltrim($page->path, '/'), '/') . '/' : '') . $page->filename,
                    'document_root' => $website->getDocumentRoot(),
                ]);
            if (!$response->successful()) {
                throw new \Exception('Page removal failed: ' . $response->body());
            }
        }

        if (!$response->successful()) {
            throw new \Exception('Page removal failed: ' . $response->body());
        }

        Log::info("Page removed successfully", ['page_id' => $page->id]);
    }

    public function removePageBy(Website $website, string $path, string $filename): void
    {
        $vps = $website->vpsServer;
        if (!$vps->isActive()) {
            throw new \Exception('VPS server is not active');
        }

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'X-Worker-Key' => $vps->worker_key,
                    'Content-Type' => 'application/json',
                ])
                ->post("http://{$vps->ip_address}:8080/api/remove-page", [
                    'website_id' => $website->id,
                    'page_path' => $path,
                    'filename' => (ltrim($path, '/') ? rtrim(ltrim($path, '/'), '/') . '/' : '') . $filename,
                    'document_root' => $website->getDocumentRoot(),
                ]);

            if (!$response->successful()) {
                throw new \Exception('Page removal failed: ' . $response->body());
            }
        } catch (ConnectionException $e) {
            $response = Http::timeout(60)
                ->withHeaders([
                    'X-Worker-Key' => $vps->worker_key,
                    'Content-Type' => 'application/json',
                ])
                ->post("http://127.0.0.1:8080/api/remove-page", [
                    'website_id' => $website->id,
                    'page_path' => $path,
                    'filename' => (ltrim($path, '/') ? rtrim(ltrim($path, '/'), '/') . '/' : '') . $filename,
                    'document_root' => $website->getDocumentRoot(),
                ]);
            if (!$response->successful()) {
                throw new \Exception('Page removal failed: ' . $response->body());
            }
        }
    }

    public function removeWebsite(Website $website): void
    {
        $vps = $website->vpsServer;

        if (!$vps->isActive()) {
            return; // Skip if VPS is not active
        }

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'X-Worker-Key' => $vps->worker_key,
                    'Content-Type' => 'application/json',
                ])
                ->post("http://{$vps->ip_address}:8080/api/remove-website", [
                    'website_id' => $website->id,
                    'domain' => $website->domain,
                    'document_root' => $website->getDocumentRoot(),
                    'wordpress_config' => $website->wordpress_config,
                ]);

            if (!$response->successful()) {
                throw new \Exception($response->body());
            }
        } catch (ConnectionException $e) {
            Http::timeout(60)
                ->withHeaders([
                    'X-Worker-Key' => $vps->worker_key,
                    'Content-Type' => 'application/json',
                ])
                ->post("http://127.0.0.1:8080/api/remove-website", [
                    'website_id' => $website->id,
                    'domain' => $website->domain,
                    'document_root' => $website->getDocumentRoot(),
                    'wordpress_config' => $website->wordpress_config,
                ]);
        } catch (\Exception $e) {
            Log::warning("Failed to remove website from VPS", [
                'website_id' => $website->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function deactivateWebsite(Website $website): void
    {
        $vps = $website->vpsServer;

        if (!$vps->isActive()) {
            return;
        }

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'X-Worker-Key' => $vps->worker_key,
                    'Content-Type' => 'application/json',
                ])
                ->post("http://{$vps->ip_address}:8080/api/deactivate-website", [
                    'domain' => $website->domain,
                ]);

            if (!$response->successful()) {
                throw new \Exception($response->body());
            }
        } catch (ConnectionException $e) {
            Http::timeout(60)
                ->withHeaders([
                    'X-Worker-Key' => $vps->worker_key,
                    'Content-Type' => 'application/json',
                ])
                ->post("http://127.0.0.1:8080/api/deactivate-website", [
                    'domain' => $website->domain,
                ]);
        }
    }

    private function generateNginxConfig(Website $website): string
    {
        $domain = $website->domain;
        $documentRoot = $website->getDocumentRoot();
        $phpSocket = '/var/run/php/php8.2-fpm.sock';

        $config = "server {\n";
        $config .= "    listen 80;\n";
        $config .= "    server_name {$domain} www.{$domain};\n";
        $config .= "    root {$documentRoot};\n";
        $config .= "    index index.html index.htm index.php;\n\n";

        $config .= "    location ^~ /.well-known/acme-challenge/ {\n";
        $config .= "        root {$documentRoot};\n";
        $config .= "        default_type \"text/plain\";\n";
        $config .= "        try_files \$uri =404;\n";
        $config .= "    }\n\n";

        if ($website->type === 'wordpress') {
            $config .= "    location / {\n";
            $config .= "        try_files \$uri \$uri/ /index.php?\$args;\n";
            $config .= "    }\n\n";

            $config .= "    location ~ \\.php\$ {\n";
            $config .= "        include snippets/fastcgi-php.conf;\n";
            $config .= "        fastcgi_pass unix:{$phpSocket};\n";
            $config .= "    }\n\n";

            $config .= "    location ~ /\\.ht {\n";
            $config .= "        deny all;\n";
            $config .= "    }\n";
        } else {
            $config .= "    location / {\n";
            $config .= "        try_files \$uri \$uri/ \$uri/index.html =404;\n";
            $config .= "    }\n";
        }

        $config .= "}\n";

        return $config;
    }

    /**
     * Generate proper URL for a page
     * If page belongs to a subdomain, return full URL
     * If page belongs to same website, return relative path
     */
    private function generatePageUrl(Page $page, Website $currentWebsite): string
    {
        $pageWebsite = $page->website;

        // If page belongs to a different website (subdomain), use full URL
        if ($pageWebsite->id !== $currentWebsite->id) {
            $protocol = $pageWebsite->ssl_enabled ? 'https://' : 'http://';
            return $protocol . $pageWebsite->domain . $page->path;
        }

        // Same website, use relative path
        return $page->path;
    }

    public function publishAllPages(Website $website): void
    {
        foreach ($website->pages as $page) {
            $this->deployPage($page);
        }
    }
}