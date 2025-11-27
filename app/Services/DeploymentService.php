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

        // Deploy laravel1 assets/pages only for MAIN domain (avoid per-subdomain assets)
        if ($website->type === 'laravel1') {
            $parts = explode('.', $website->domain);
            $isSubdomain = count($parts) > 2;
            if (!$isSubdomain) {
                $this->deployTemplateAssets($website, 'home-1');
                $this->deployTemplateAssets($website, 'listing-1');
                $this->deployTemplateAssets($website, 'hotel-detail-1');
                $this->deployLaravel1Homepage($website);
                $this->deployLaravel1AllCategories($website);
            }
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
            $folderPages = $folder->pages()->limit(4)->get();
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
            $folderPages = $folder->pages()->orderBy('updated_at', 'desc')->limit(8)->get();
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

        $sharedDir = public_path('templates/_shared');
        $sharedHeader = @file_get_contents($sharedDir . '/header.html');
        $sharedFooter = @file_get_contents($sharedDir . '/footer.html');
        if ($sharedHeader) {
            $headerPattern = '/<header[^>]*>.*?<\/header>/s';
            if (preg_match($headerPattern, $html)) {
                $html = preg_replace($headerPattern, $sharedHeader, $html);
            } elseif (preg_match('/<body[^>]*>/i', $html)) {
                $html = preg_replace('/(<body[^>]*>)/i', '$1' . $sharedHeader, $html, 1);
            }
        }
        if ($sharedFooter) {
            $footerPattern = '/(?:<!--\s*Footer\s*-->\\s*)?<footer[^>]*>.*?<\/footer>/s';
            if (preg_match($footerPattern, $html)) {
                $html = preg_replace($footerPattern, $sharedFooter, $html);
            } elseif (stripos($html, '</body>') !== false) {
                $html = preg_replace('/<\/body>/i', $sharedFooter . '</body>', $html, 1);
            } else {
                $html .= "\n" . $sharedFooter;
            }
        }

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

        // Rewrite CSS/JS to shared main domain assets (home-1)
        $protocol = $website->ssl_enabled ? 'https://' : 'http://';
        $base = $protocol . $website->domain;
        $html = preg_replace(
            '#href="/templates/home\-1/style\.css[^\"]*"#',
            'href="' . $base . '/templates/home-1/style.css?v={{SCRIPT_VERSION}}"',
            $html
        );
        $html = preg_replace(
            '#src="/templates/home\-1/script\.js[^\"]*"#',
            'src="' . $base . '/templates/home-1/script.js?v={{SCRIPT_VERSION}}"',
            $html
        );
        $html = str_replace('{{SCRIPT_VERSION}}', time(), $html);

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
            $response = Http::timeout(60)
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

        if (!$response->successful()) {
            throw new \Exception('Homepage deployment failed: ' . $response->body());
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

        $sharedDir = public_path('templates/_shared');
        $sharedHeader = @file_get_contents($sharedDir . '/header.html');
        $sharedFooter = @file_get_contents($sharedDir . '/footer.html');
        if ($sharedHeader) {
            $headerPattern = '/<header[^>]*>.*?<\/header>/s';
            if (preg_match($headerPattern, $html)) {
                $html = preg_replace($headerPattern, $sharedHeader, $html);
            } elseif (preg_match('/<body[^>]*>/i', $html)) {
                $html = preg_replace('/(<body[^>]*>)/i', '$1' . $sharedHeader, $html, 1);
            }
        }
        if ($sharedFooter) {
            $footerPattern = '/(?:<!--\s*Footer\s*-->\\s*)?<footer[^>]*>.*?<\/footer>/s';
            if (preg_match($footerPattern, $html)) {
                $html = preg_replace($footerPattern, $sharedFooter, $html);
            } elseif (stripos($html, '</body>') !== false) {
                $html = preg_replace('/<\/body>/i', $sharedFooter . '</body>', $html, 1);
            } else {
                $html .= "\n" . $sharedFooter;
            }
        }

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

        // Rewrite CSS/JS to shared main domain assets (listing-1)
        $protocol = $website->ssl_enabled ? 'https://' : 'http://';
        $base = $protocol . $website->domain;
        $html = preg_replace(
            '#href="/templates/listing\-1/style\.css[^\"]*"#',
            'href="' . $base . '/templates/listing-1/style.css?v={{SCRIPT_VERSION}}"',
            $html
        );
        $html = preg_replace(
            '#src="/templates/listing\-1/script\.js[^\"]*"#',
            'src="' . $base . '/templates/listing-1/script.js?v={{SCRIPT_VERSION}}"',
            $html
        );
        $html = str_replace('{{SCRIPT_VERSION}}', time(), $html);

        try {
            $response = Http::timeout(60)
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
            $response = Http::timeout(60)
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

        if (!$response->successful()) {
            throw new \Exception('Category deployment failed: ' . $response->body());
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

        // Detect template name early
        $templateName = null;
        if (!empty($page->template_type)) {
            $templateName = $page->template_type === 'hotel-detail' ? 'hotel-detail-1' : $page->template_type;
        } else {
            if (preg_match('/\/templates\/([^\/]+)\//', $html, $m)) {
                $templateName = $m[1] ?? null;
            }
        }

        // If this is a template-based page, always start from latest template file and shared header/footer
        if (!empty($templateName)) {
            $templatePath = public_path("templates/{$templateName}/index.html");
            if (file_exists($templatePath)) {
                $html = file_get_contents($templatePath) ?: $html;

                $sharedDir = public_path('templates/_shared');
                $sharedHeader = @file_get_contents($sharedDir . '/header.html');
                $sharedFooter = @file_get_contents($sharedDir . '/footer.html');

                if ($sharedHeader) {
                    $headerPattern = '/<header[^>]*>[\s\S]*?<\/header>/i';
                    if (preg_match($headerPattern, $html)) {
                        $html = preg_replace($headerPattern, $sharedHeader, $html);
                    } elseif (preg_match('/<body[^>]*>/i', $html)) {
                        $html = preg_replace('/(<body[^>]*>)/i', '$1' . $sharedHeader, $html, 1);
                    }
                }
                if ($sharedFooter) {
                    $footerPattern = '/(?:<!--\s*Footer\s*-->\s*)?<footer[^>]*>[\s\S]*?<\/footer>/i';
                    if (preg_match($footerPattern, $html)) {
                        $html = preg_replace($footerPattern, $sharedFooter, $html);
                    } elseif (stripos($html, '</body>') !== false) {
                        $html = preg_replace('/<\/body>/i', $sharedFooter . '</body>', $html, 1);
                    } else {
                        $html .= "\n" . $sharedFooter;
                    }
                }
            }
        }

        // Add primary folder info for breadcrumb
        // Use primary folder if set, otherwise use deepest folder (most nested)
        $folder = null;
        if ($page->primary_folder_id) {
            $folder = $page->primaryFolder;
        } else {
            // Find the folder with maximum depth (most ancestors)
            $folders = $page->folders;
            $maxDepth = -1;

            foreach ($folders as $f) {
                // Calculate depth by counting ancestors
                $depth = 0;
                $current = $f;
                while ($current->parent) {
                    $depth++;
                    $current = $current->parent;
                }

                if ($depth > $maxDepth) {
                    $maxDepth = $depth;
                    $folder = $f;
                }
            }

            // If no folder found, use first folder
            if (!$folder) {
                $folder = $folders->first();
            }
        }

        if ($folder) {
            $data['primary_folder_name'] = $folder->name;
            $data['primary_folder_path'] = $folder->getPath();

            // Generate breadcrumb_items from folder hierarchy
            $breadcrumbItems = ['Home'];
            $breadcrumbPaths = [''];  // Home path is root

            // Get all parent folders in order
            $folderHierarchy = [];
            $currentFolder = $folder;
            while ($currentFolder) {
                array_unshift($folderHierarchy, $currentFolder);
                $currentFolder = $currentFolder->parent;
            }

            // Add folder names and paths to breadcrumb
            foreach ($folderHierarchy as $f) {
                $breadcrumbItems[] = $f->name;
                $breadcrumbPaths[] = $f->getPath();
            }

            // Add page title (no path for current page)
            $breadcrumbItems[] = $data['title'] ?? $page->title ?? 'Untitled';
            $breadcrumbPaths[] = '';  // Last item has no link

            // Update breadcrumb data
            $data['breadcrumb_items'] = $breadcrumbItems;
            $data['breadcrumb_paths'] = $breadcrumbPaths;
        }

        // Add main domain URL for breadcrumb
        $website = $page->website;
        $domainParts = explode('.', $website->domain);
        $mainDomain = count($domainParts) > 2 ? implode('.', array_slice($domainParts, -2)) : $website->domain;
        $protocol = $website->ssl_enabled ? 'https://' : 'http://';
        $data['main_domain_url'] = $protocol . $mainDomain;

        // Sync title from page model to template data for consistency
        $data['title'] = $page->title ?? ($data['title'] ?? 'Untitled');

        // Replace placeholders
        $title = $data['title'] ?? 'Untitled';
        $description = $data['about1'] ?? $page->meta_description ?? '';
        $gallery = $data['gallery'] ?? [];

        // Title replacement: first try placeholder, then fallback to <title> tag
        $html = str_replace('{{TITLE}}', e($title), $html);
        if (strpos($html, '{{TITLE}}') === false) {
            $html = preg_replace('/<title>[\s\S]*?<\/title>/i', '<title>' . e($title) . '</title>', $html, 1);
        }

        $html = str_replace('{{DESCRIPTION}}', e($description), $html);
        $html = str_replace('{{OG_IMAGE}}', $gallery[0] ?? '', $html);
        $html = str_replace('{{OG_URL}}', 'https://' . $website->domain . $page->path, $html);

        // Inject page data script
        $dataScript = '<script type="application/json" id="page-data">' . json_encode($data) . '</script>';

        // Replace known placeholders or fallback
        if (strpos($html, '{{GALLERY_DATA_SCRIPT}}') !== false) {
            $html = str_replace('{{GALLERY_DATA_SCRIPT}}', $dataScript, $html);
        } elseif (strpos($html, '{{PAGE_DATA_SCRIPT}}') !== false) {
            $html = str_replace('{{PAGE_DATA_SCRIPT}}', $dataScript, $html);
        } else {
            // If no placeholder, replace hardcoded script tag if present
            $replaced = preg_replace(
                '/<script[^>]*id=["\']page-data["\'][^>]*>.*?<\/script>/s',
                $dataScript,
                $html
            );
            if ($replaced !== null) {
                $html = $replaced;
            }
            // If still no page-data script, inject before </body>
            if (strpos($html, 'id="page-data"') === false) {
                if (stripos($html, '</body>') !== false) {
                    $html = preg_replace('/<\/body>/i', $dataScript . '</body>', $html, 1);
                } else {
                    $html .= "\n" . $dataScript;
                }
            }
        }

        // Rewrite CSS/JS asset references to use shared assets on main domain
        if (!empty($templateName)) {
            $base = $data['main_domain_url'] ?? ($website->ssl_enabled ? 'https://' : 'http://') . $mainDomain;
            // Rewrite template-based URLs to absolute main domain with SCRIPT_VERSION placeholder
            $html = preg_replace(
                '#href="/templates/' . preg_quote($templateName, '#') . '/style\.css[^\"]*"#',
                'href="' . $base . '/templates/' . $templateName . '/style.css?v={{SCRIPT_VERSION}}"',
                $html
            );
            $html = preg_replace(
                '#src="/templates/' . preg_quote($templateName, '#') . '/script\.js[^\"]*"#',
                'src="' . $base . '/templates/' . $templateName . '/script.js?v={{SCRIPT_VERSION}}"',
                $html
            );
            // Rewrite page-local asset names to shared main domain template assets
            $html = preg_replace(
                '#href="style\.css[^\"]*"#',
                'href="' . $base . '/templates/' . $templateName . '/style.css?v={{SCRIPT_VERSION}}"',
                $html
            );
            $html = preg_replace(
                '#src="script\.js[^\"]*"#',
                'src="' . $base . '/templates/' . $templateName . '/script.js?v={{SCRIPT_VERSION}}"',
                $html
            );
        }

        // Add script version for cache busting
        $html = str_replace('{{SCRIPT_VERSION}}', time(), $html);

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
            $response = null;
            try {
                $response = Http::timeout(30)
                    ->withHeaders(['X-Worker-Key' => $vps->worker_key, 'Content-Type' => 'application/json'])
                    ->post("http://{$vps->ip_address}:8080/api/deploy-page", [
                        'website_id' => $website->id,
                        'page_path' => "/templates/{$templateName}",
                        'filename' => $file,
                        'content' => $content,
                        'document_root' => $website->getDocumentRoot(),
                    ]);
            } catch (ConnectionException $e) {
                $response = Http::timeout(30)
                    ->withHeaders(['X-Worker-Key' => $vps->worker_key, 'Content-Type' => 'application/json'])
                    ->post("http://127.0.0.1:8080/api/deploy-page", [
                        'website_id' => $website->id,
                        'page_path' => "/templates/{$templateName}",
                        'filename' => $file,
                        'content' => $content,
                        'document_root' => $website->getDocumentRoot(),
                    ]);
            }

            if (!$response || !$response->successful()) {
                \Log::error('Failed to deploy template asset', [
                    'domain' => $website->domain,
                    'template' => $templateName,
                    'file' => $file,
                    'response' => $response ? $response->body() : 'no response',
                ]);
                throw new \Exception('Asset deployment failed for ' . $file . ' (' . $templateName . ')');
            }
        }
    }

    /**
     * Deploy CSS/JS assets into a specific page directory and rewrite asset references
     */
    public function deployPageAssets(Page $page, string $templateName): void
    {
        $website = $page->website;
        $vps = $website->vpsServer;
        if (!$vps || !$vps->isActive()) {
            throw new \Exception('VPS server is not active');
        }

        $templateDir = public_path("templates/{$templateName}");
        $files = ['style.css', 'script.js'];

        foreach ($files as $file) {
            $filePath = "{$templateDir}/{$file}";
            if (!file_exists($filePath)) continue;

            $content = file_get_contents($filePath);
            $response = null;
            try {
                $response = Http::timeout(30)
                    ->withHeaders(['X-Worker-Key' => $vps->worker_key, 'Content-Type' => 'application/json'])
                    ->post("http://{$vps->ip_address}:8080/api/deploy-page", [
                        'website_id' => $website->id,
                        'page_path' => $page->path,
                        'filename' => $file,
                        'content' => $content,
                        'document_root' => $website->getDocumentRoot(),
                    ]);
            } catch (ConnectionException $e) {
                $response = Http::timeout(30)
                    ->withHeaders(['X-Worker-Key' => $vps->worker_key, 'Content-Type' => 'application/json'])
                    ->post("http://127.0.0.1:8080/api/deploy-page", [
                        'website_id' => $website->id,
                        'page_path' => $page->path,
                        'filename' => $file,
                        'content' => $content,
                        'document_root' => $website->getDocumentRoot(),
                    ]);
            }

            if (!$response || !$response->successful()) {
                \Log::error('Failed to deploy page asset', [
                    'domain' => $website->domain,
                    'template' => $templateName,
                    'page_id' => $page->id,
                    'file' => $file,
                    'response' => $response ? $response->body() : 'no response',
                ]);
                throw new \Exception('Asset deployment failed for page ' . $page->id . ' - ' . $file);
            }
        }

        // Rewrite asset references in page content to use local files in the page directory
        $html = $page->content;
        $html = preg_replace(
            '#href="/templates/' . preg_quote($templateName, '#') . '/style\.css[^"]*"#',
            'href="style.css?v={{SCRIPT_VERSION}}"',
            $html
        );
        $html = preg_replace(
            '#src="/templates/' . preg_quote($templateName, '#') . '/script\.js[^"]*"#',
            'src="script.js?v={{SCRIPT_VERSION}}"',
            $html
        );

        // Persist and redeploy page
        $page->content = $html;
        $page->save();
        $this->deployPage($page);
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
            $pending = dispatch(function () use ($page) {
                app(\App\Services\DeploymentService::class)->deployPage($page);
            });
            if (method_exists($pending, 'afterResponse')) { $pending->afterResponse(); }
        }
    }
}
