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
    /**
     * Get the template package for a website
     */
    private function getTemplatePackage(Website $website): ?string
    {
        return $website->template_package ?? 'laravel-hotel-1';
    }

    /**
     * Get template file path based on package and type
     */
    private function getTemplatePath(Website $website, string $type): string
    {
        $package = $this->getTemplatePackage($website);
        return public_path("templates/{$package}/{$type}/index.html");
    }

    /**
     * Get shared template path (header/footer)
     */
    private function getSharedPath(Website $website, string $file): string
    {
        $package = $this->getTemplatePackage($website);
        return public_path("templates/{$package}/shared/{$file}");
    }

    /**
     * Get asset URLs for a template type
     */
    private function getAssetUrls(Website $website, string $type): array
    {
        $package = $this->getTemplatePackage($website);
        $protocol = $website->ssl_enabled ? 'https://' : 'http://';
        $base = $protocol . $website->domain;

        return [
            'css' => "{$base}/templates/{$package}/assets/{$type}.css?v=" . time(),
            'js' => "{$base}/templates/{$package}/assets/{$type}.js?v=" . time(),
        ];
    }

    /**
     * Convert control panel asset URL to local VPS path or root domain URL
     * For use in deployed HTML on the VPS
     *
     * - Main domain: returns local path like "/assets/logo.png"
     * - Subdomain: returns full URL to root domain like "https://rootdomain.com/assets/logo.png"
     */
    private function convertToLocalAssetUrl(string $settingKey, ?string $url, Website $website): ?string
    {
        if (empty($url)) {
            return null;
        }

        // Extract filename from URL
        $filename = basename(parse_url($url, PHP_URL_PATH));

        // Check if this is a subdomain
        $parts = explode('.', $website->domain);
        $isSubdomain = count($parts) > 2;

        // Build the path/URL
        if ($settingKey === 'favicon_url') {
            $path = '/' . $filename;
        } else {
            // logo_header_url, logo_footer_url
            $path = '/assets/' . $filename;
        }

        // Subdomain: return full URL to root domain
        if ($isSubdomain) {
            $rootDomain = $parts[count($parts)-2] . '.' . $parts[count($parts)-1];
            $protocol = $website->ssl_enabled ? 'https://' : 'http://';
            return $protocol . $rootDomain . $path;
        }

        // Main domain: return local path
        return $path;
    }

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
                $this->deployTemplatePackageAssets($website);
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

    public function deployLaravel1AllPages(Website $website): void
    {
        $pages = \App\Models\Page::whereHas('folder', function ($query) use ($website) {
            $query->where('website_id', $website->id);
        })->get();

        foreach ($pages as $page) {
            $this->deployPage($page);
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

        $templatePath = $this->getTemplatePath($website, 'home');
        $html = file_exists($templatePath) ? file_get_contents($templatePath) : '<h1>Template not found</h1>';

        $sharedHeader = @file_get_contents($this->getSharedPath($website, 'header.html'));
        $sharedFooter = @file_get_contents($this->getSharedPath($website, 'footer.html'));
        $siteSettings = $this->getMainSettings($website);

        // Header now includes <head>, apply settings to both
        if ($sharedHeader) {
            // Apply favicon to <head> - use local VPS path
            if (!empty($siteSettings['favicon_url'])) {
                $localFaviconUrl = $this->convertToLocalAssetUrl('favicon_url', $siteSettings['favicon_url'], $website);
                $sharedHeader = preg_replace('/<\/head>/i', '<link rel="icon" href="' . e($localFaviconUrl) . '"></head>', $sharedHeader, 1);
            }
            // Apply custom head HTML
            if (!empty($siteSettings['custom_head_html'])) {
                $sharedHeader = preg_replace('/<\/head>/i', $siteSettings['custom_head_html'] . '</head>', $sharedHeader, 1);
            }
            // Apply logo/title to <header> - use local VPS path
            if (!empty($siteSettings['logo_header_url'])) {
                $localLogoUrl = $this->convertToLocalAssetUrl('logo_header_url', $siteSettings['logo_header_url'], $website);
                $protocol = $website->ssl_enabled ? 'https://' : 'http://';
                $homeUrl = $protocol . $website->domain . '/';
                $sharedHeader = preg_replace('/<h1[^>]*id=["\']site-name["\'][^>]*>[\s\S]*?<\/h1>/i', '<a href="' . e($homeUrl) . '"><img id="site-logo-header" src="' . e($localLogoUrl) . '" alt="' . e($siteSettings['title'] ?? $website->domain) . '" class="h-8"></a>', $sharedHeader, 1);
            } elseif (!empty($siteSettings['title'])) {
                $protocol = $website->ssl_enabled ? 'https://' : 'http://';
                $homeUrl = $protocol . $website->domain . '/';
                $sharedHeader = preg_replace('/<h1[^>]*id=["\']site-name["\'][^>]*>[\s\S]*?<\/h1>/i', '<a href="' . e($homeUrl) . '"><h1 id="site-name" class="text-2xl font-bold">' . e($siteSettings['title']) . '</h1></a>', $sharedHeader, 1);
            }
            // Apply menu
            if (!empty($siteSettings['menu_html'])) {
                $sharedHeader = preg_replace('/(<nav[^>]*>)[\s\S]*?(<\/nav>)/i', '$1' . $siteSettings['menu_html'] . '$2', $sharedHeader, 1);
            } elseif (!empty($siteSettings['menu'])) {
                $protocol = $website->ssl_enabled ? 'https://' : 'http://';
                $base = $protocol . $website->domain;
                $menuHtml = $this->generateMenuHtml($siteSettings['menu'], $base);
                $sharedHeader = preg_replace('/(<nav[^>]*>)[\s\S]*?(<\/nav>)/i', '$1' . $menuHtml . '$2', $sharedHeader, 1);
            }

            // Inject header (with <head>) before <body>
            if (preg_match('/<body[^>]*>/i', $html)) {
                $html = preg_replace('/(<body[^>]*>)/i', $sharedHeader . '$1', $html, 1);
            } else {
                $html = $sharedHeader . $html;
            }
        } else {
            // Fallback if no header found
            $fallbackHead = '<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{TITLE}}</title><meta name="description" content="{{DESCRIPTION}}"><meta property="og:title" content="{{TITLE}}"><meta property="og:description" content="{{DESCRIPTION}}"><meta property="og:image" content="{{OG_IMAGE}}"><meta property="og:url" content="{{OG_URL}}"></head>';
            if (preg_match('/<body[^>]*>/i', $html)) {
                $html = preg_replace('/(<body[^>]*>)/i', $fallbackHead . '$1', $html, 1);
            } else {
                $html = $fallbackHead . $html;
            }
        }

        if ($sharedFooter) {
            // Apply logo to footer - use local VPS path
            if (!empty($siteSettings['logo_footer_url'])) {
                $localLogoFooterUrl = $this->convertToLocalAssetUrl('logo_footer_url', $siteSettings['logo_footer_url'], $website);
                $sharedFooter = preg_replace('/<h3[^>]*class=["\']text-xl[^>]*>[\s\S]*?<\/h3>/i', '<img id="site-logo-footer" src="' . e($localLogoFooterUrl) . '" alt="' . e($siteSettings['title'] ?? $website->domain) . '" class="h-10">', $sharedFooter, 1);
            } elseif (!empty($siteSettings['title'])) {
                $sharedFooter = preg_replace('/<h3[^>]*class=["\']text-xl[^>]*>[\s\S]*?<\/h3>/i', '<h3 class="text-xl font-bold mb-4">' . e($siteSettings['title']) . '</h3>', $sharedFooter, 1);
            }
            $footerPattern = '/(?:<!--\s*Footer\s*-->\\s*)?<footer[^>]*>.*?<\/footer>/s';
            if (preg_match($footerPattern, $html)) {
                $html = preg_replace($footerPattern, $sharedFooter, $html);
            } elseif (stripos($html, '</body>') !== false) {
                $html = preg_replace('/<\/body>/i', $sharedFooter . '</body>', $html, 1);
            } else {
                $html .= "\n" . $sharedFooter;
            }
        }

        $html = str_replace('{{TITLE}}', e($siteSettings['title'] ?? $website->domain), $html);
        $html = str_replace('{{DESCRIPTION}}', 'Find your perfect stay at ' . e($website->domain), $html);
        $html = str_replace('{{OG_IMAGE}}', $featuredData[0]['image'] ?? '', $html);
        $html = str_replace('{{OG_URL}}', 'https://' . $website->domain, $html);

        $dataScript = '<script type="application/json" id="page-data">' . json_encode([
            'categories' => $categoriesData,
            'featured' => $featuredData,
            'newest' => $newestData,
        ]) . '</script>';
        $html = str_replace('{{PAGE_DATA_SCRIPT}}', $dataScript, $html);

        // Rewrite CSS/JS to shared main domain assets
        $package = $this->getTemplatePackage($website);
        $protocol = $website->ssl_enabled ? 'https://' : 'http://';
        $base = $protocol . $website->domain;
        $scriptVersion = time();

        $html = preg_replace(
            '#href="[^"]*style\.css[^"]*"#',
            'href="' . $base . '/templates/' . $package . '/assets/home.css?v=' . $scriptVersion . '"',
            $html
        );
        $html = preg_replace(
            '#src="[^"]*script\.js[^"]*"#',
            'src="' . $base . '/templates/' . $package . '/assets/home.js?v=' . $scriptVersion . '"',
            $html
        );
        $html = str_replace('{{SCRIPT_VERSION}}', $scriptVersion, $html);
        $html = $this->applyWebsiteSettingsHtml($html, $siteSettings, $website);

        if (!empty($siteSettings['custom_body_html'])) {
            if (preg_match('/<body[^>]*>/i', $html)) {
                $html = preg_replace('/(<body[^>]*>)/i', '$1' . $siteSettings['custom_body_html'], $html, 1);
            } else {
                $html = $siteSettings['custom_body_html'] . $html;
            }
        }
        if (!empty($siteSettings['footer_links_html']) || !empty($siteSettings['custom_footer_html'])) {
            $injected = ($siteSettings['footer_links_html'] ?? '') . ($siteSettings['custom_footer_html'] ?? '');
            if (preg_match('/<footer[^>]*>[\s\S]*?<\/footer>/i', $html)) {
                $html = preg_replace('/(<footer[^>]*>[\s\S]*?)(<\/footer>)/i', '$1' . $injected . '$2', $html, 1);
            } elseif (stripos($html, '</body>') !== false) {
                $html = preg_replace('/<\/body>/i', $injected . '</body>', $html, 1);
            } else {
                $html .= $injected;
            }
        }

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

        $templatePath = $this->getTemplatePath($website, 'listing');
        $html = file_exists($templatePath) ? file_get_contents($templatePath) : '<h1>Template not found</h1>';

        $sharedHeader = @file_get_contents($this->getSharedPath($website, 'header.html'));
        $sharedFooter = @file_get_contents($this->getSharedPath($website, 'footer.html'));
        $siteSettings = $this->getMainSettings($website);

        // Header now includes <head>, apply settings to both
        if ($sharedHeader) {
            // Apply favicon to <head> - use local VPS path
            if (!empty($siteSettings['favicon_url'])) {
                $localFaviconUrl = $this->convertToLocalAssetUrl('favicon_url', $siteSettings['favicon_url'], $website);
                $sharedHeader = preg_replace('/<\/head>/i', '<link rel="icon" href="' . e($localFaviconUrl) . '"></head>', $sharedHeader, 1);
            }
            // Apply custom head HTML
            if (!empty($siteSettings['custom_head_html'])) {
                $sharedHeader = preg_replace('/<\/head>/i', $siteSettings['custom_head_html'] . '</head>', $sharedHeader, 1);
            }
            // Apply logo/title to <header> - use local VPS path
            if (!empty($siteSettings['logo_header_url'])) {
                $localLogoUrl = $this->convertToLocalAssetUrl('logo_header_url', $siteSettings['logo_header_url'], $website);
                $protocol = $website->ssl_enabled ? 'https://' : 'http://';
                $homeUrl = $protocol . $website->domain . '/';
                $sharedHeader = preg_replace('/<h1[^>]*id=["\']site-name["\'][^>]*>[\s\S]*?<\/h1>/i', '<a href="' . e($homeUrl) . '"><img id="site-logo-header" src="' . e($localLogoUrl) . '" alt="' . e($siteSettings['title'] ?? $website->domain) . '" class="h-8"></a>', $sharedHeader, 1);
            } elseif (!empty($siteSettings['title'])) {
                $protocol = $website->ssl_enabled ? 'https://' : 'http://';
                $homeUrl = $protocol . $website->domain . '/';
                $sharedHeader = preg_replace('/<h1[^>]*id=["\']site-name["\'][^>]*>[\s\S]*?<\/h1>/i', '<a href="' . e($homeUrl) . '"><h1 id="site-name" class="text-2xl font-bold">' . e($siteSettings['title']) . '</h1></a>', $sharedHeader, 1);
            }
            // Apply menu
            if (!empty($siteSettings['menu_html'])) {
                $sharedHeader = preg_replace('/(<nav[^>]*>)[\s\S]*?(<\/nav>)/i', '$1' . $siteSettings['menu_html'] . '$2', $sharedHeader, 1);
            } elseif (!empty($siteSettings['menu'])) {
                $protocol = $website->ssl_enabled ? 'https://' : 'http://';
                $base = $protocol . $website->domain;
                $menuHtml = $this->generateMenuHtml($siteSettings['menu'], $base);
                $sharedHeader = preg_replace('/(<nav[^>]*>)[\s\S]*?(<\/nav>)/i', '$1' . $menuHtml . '$2', $sharedHeader, 1);
            }

            // Inject header (with <head>) before <body>
            if (preg_match('/<body[^>]*>/i', $html)) {
                $html = preg_replace('/(<body[^>]*>)/i', $sharedHeader . '$1', $html, 1);
            } else {
                $html = $sharedHeader . $html;
            }
        } else {
            // Fallback if no header found
            $fallbackHead = '<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{TITLE}}</title><meta name="description" content="{{DESCRIPTION}}"><meta property="og:title" content="{{TITLE}}"><meta property="og:description" content="{{DESCRIPTION}}"><meta property="og:image" content="{{OG_IMAGE}}"><meta property="og:url" content="{{OG_URL}}"></head>';
            if (preg_match('/<body[^>]*>/i', $html)) {
                $html = preg_replace('/(<body[^>]*>)/i', $fallbackHead . '$1', $html, 1);
            } else {
                $html = $fallbackHead . $html;
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

        // Rewrite CSS/JS to shared main domain assets
        $package = $this->getTemplatePackage($website);
        $protocol = $website->ssl_enabled ? 'https://' : 'http://';
        $base = $protocol . $website->domain;
        $scriptVersion = time();

        $html = preg_replace(
            '#href="[^"]*style\.css[^"]*"#',
            'href="' . $base . '/templates/' . $package . '/assets/listing.css?v=' . $scriptVersion . '"',
            $html
        );
        $html = preg_replace(
            '#src="[^"]*script\.js[^"]*"#',
            'src="' . $base . '/templates/' . $package . '/assets/listing.js?v=' . $scriptVersion . '"',
            $html
        );
        $html = str_replace('{{SCRIPT_VERSION}}', $scriptVersion, $html);
        if (!empty($siteSettings['custom_body_html'])) {
            if (preg_match('/<body[^>]*>/i', $html)) {
                $html = preg_replace('/(<body[^>]*>)/i', '$1' . $siteSettings['custom_body_html'], $html, 1);
            } else {
                $html = $siteSettings['custom_body_html'] . $html;
            }
        }
        if (!empty($siteSettings['footer_links_html']) || !empty($siteSettings['custom_footer_html'])) {
            $injected = ($siteSettings['footer_links_html'] ?? '') . ($siteSettings['custom_footer_html'] ?? '');
            if (preg_match('/<footer[^>]*>[\s\S]*?<\/footer>/i', $html)) {
                $html = preg_replace('/(<footer[^>]*>[\s\S]*?)(<\/footer>)/i', '$1' . $injected . '$2', $html, 1);
            } elseif (stripos($html, '</body>') !== false) {
                $html = preg_replace('/<\/body>/i', $injected . '</body>', $html, 1);
            } else {
                $html .= $injected;
            }
        }

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

        // Detect template type
        $templateType = null;
        if (!empty($page->template_type) && $page->template_type !== 'blank') {
            // Use explicit template type (home, listing, detail)
            $templateType = $page->template_type;
        } elseif ($page->template_type !== 'blank') {
            // Auto-detect from existing HTML or default to detail for laravel1 sites
            if (preg_match('/\/templates\/[^\/]+\/(home|listing|detail)\//', $html, $m)) {
                $templateType = $m[1] ?? null;
            } elseif ($page->website->type === 'laravel1') {
                // Default to detail for laravel1 websites
                $templateType = 'detail';
            }
        }

        // If this is a template-based page, load template from package
        if (!empty($templateType) && $page->website->type === 'laravel1') {
            $templatePath = $this->getTemplatePath($page->website, $templateType);
            if (file_exists($templatePath)) {
                $html = file_get_contents($templatePath) ?: $html;

                $sharedHeader = @file_get_contents($this->getSharedPath($page->website, 'header.html'));
                $sharedFooter = @file_get_contents($this->getSharedPath($page->website, 'footer.html'));
                $siteSettings = $this->getMainSettings($page->website);

                // Header now includes <head>, apply settings to both
                if ($sharedHeader) {
                    // Apply favicon to <head> - use local VPS path
                    if (!empty($siteSettings['favicon_url'])) {
                        $localFaviconUrl = $this->convertToLocalAssetUrl('favicon_url', $siteSettings['favicon_url'], $page->website);
                        $sharedHeader = preg_replace('/<\/head>/i', '<link rel="icon" href="' . e($localFaviconUrl) . '"></head>', $sharedHeader, 1);
                    }
                    // Apply custom head HTML
                    if (!empty($siteSettings['custom_head_html'])) {
                        $sharedHeader = preg_replace('/<\/head>/i', $siteSettings['custom_head_html'] . '</head>', $sharedHeader, 1);
                    }
                    // Apply logo/title to <header> - use local VPS path
                    if (!empty($siteSettings['logo_header_url'])) {
                        $localLogoUrl = $this->convertToLocalAssetUrl('logo_header_url', $siteSettings['logo_header_url'], $page->website);
                        $protocol = $page->website->ssl_enabled ? 'https://' : 'http://';
                        // Always link to root domain, not subdomain
                        $domainParts = explode('.', $page->website->domain);
                        $rootDomain = count($domainParts) > 2 ? implode('.', array_slice($domainParts, -2)) : $page->website->domain;
                        $homeUrl = $protocol . $rootDomain . '/';
                        $sharedHeader = preg_replace('/<h1[^>]*id=["\']site-name["\'][^>]*>[\s\S]*?<\/h1>/i', '<a href="' . e($homeUrl) . '"><img id="site-logo-header" src="' . e($localLogoUrl) . '" alt="' . e($siteSettings['title'] ?? $page->website->domain) . '" class="h-8"></a>', $sharedHeader, 1);
                    } elseif (!empty($siteSettings['title'])) {
                        $protocol = $page->website->ssl_enabled ? 'https://' : 'http://';
                        // Always link to root domain, not subdomain
                        $domainParts = explode('.', $page->website->domain);
                        $rootDomain = count($domainParts) > 2 ? implode('.', array_slice($domainParts, -2)) : $page->website->domain;
                        $homeUrl = $protocol . $rootDomain . '/';
                        $sharedHeader = preg_replace('/<h1[^>]*id=["\']site-name["\'][^>]*>[\s\S]*?<\/h1>/i', '<a href="' . e($homeUrl) . '"><h1 id="site-name" class="text-2xl font-bold">' . e($siteSettings['title']) . '</h1></a>', $sharedHeader, 1);
                    }
                    // Apply menu
                    if (!empty($siteSettings['menu_html'])) {
                        $sharedHeader = preg_replace('/(<nav[^>]*>)[\s\S]*?(<\/nav>)/i', '$1' . $siteSettings['menu_html'] . '$2', $sharedHeader, 1);
                    } elseif (!empty($siteSettings['menu'])) {
                        $protocol = $page->website->ssl_enabled ? 'https://' : 'http://';
                        $base = $protocol . $page->website->domain;
                        $menuHtml = $this->generateMenuHtml($siteSettings['menu'], $base);
                        $sharedHeader = preg_replace('/(<nav[^>]*>)[\s\S]*?(<\/nav>)/i', '$1' . $menuHtml . '$2', $sharedHeader, 1);
                    }

                    // Inject header (with <head>) before <body>
                    if (preg_match('/<body[^>]*>/i', $html)) {
                        $html = preg_replace('/(<body[^>]*>)/i', $sharedHeader . '$1', $html, 1);
                    } else {
                        $html = $sharedHeader . $html;
                    }
                } else {
                    // Fallback if no header found
                    $fallbackHead = '<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{TITLE}}</title><meta name="description" content="{{DESCRIPTION}}"><meta property="og:title" content="{{TITLE}}"><meta property="og:description" content="{{DESCRIPTION}}"><meta property="og:image" content="{{OG_IMAGE}}"><meta property="og:url" content="{{OG_URL}}"></head>';
                    if (preg_match('/<body[^>]*>/i', $html)) {
                        $html = preg_replace('/(<body[^>]*>)/i', $fallbackHead . '$1', $html, 1);
                    } else {
                        $html = $fallbackHead . $html;
                    }
                }

                if ($sharedFooter) {
                    // Apply logo to footer - use local VPS path
                    if (!empty($siteSettings['logo_footer_url'])) {
                        $localLogoFooterUrl = $this->convertToLocalAssetUrl('logo_footer_url', $siteSettings['logo_footer_url'], $page->website);
                        $sharedFooter = preg_replace('/<h3[^>]*class=["\']text-xl[^>]*>[\s\S]*?<\/h3>/i', '<img id="site-logo-footer" src="' . e($localLogoFooterUrl) . '" alt="' . e($siteSettings['title'] ?? $page->website->domain) . '" class="h-10">', $sharedFooter, 1);
                    } elseif (!empty($siteSettings['title'])) {
                        $sharedFooter = preg_replace('/<h3[^>]*class=["\']text-xl[^>]*>[\s\S]*?<\/h3>/i', '<h3 class="text-xl font-bold mb-4">' . e($siteSettings['title']) . '</h3>', $sharedFooter, 1);
                    }
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

        // Inject page data script as JSON (detail.js reads from this)
        $dataScript = '<script type="application/json" id="page-data">' . json_encode($data, JSON_UNESCAPED_UNICODE) . '</script>';

        // Replace known placeholders or fallback
        if (strpos($html, '{{GALLERY_DATA_SCRIPT}}') !== false) {
            $html = str_replace('{{GALLERY_DATA_SCRIPT}}', $dataScript, $html);
        } elseif (strpos($html, '{{PAGE_DATA_SCRIPT}}') !== false) {
            $html = str_replace('{{PAGE_DATA_SCRIPT}}', $dataScript, $html);
        } else {
            // If no placeholder, inject before </body>
            if (stripos($html, '</body>') !== false) {
                $html = preg_replace('/<\/body>/i', $dataScript . '</body>', $html, 1);
            } else {
                $html .= "\n" . $dataScript;
            }
        }

        // Rewrite CSS/JS asset references to use absolute URLs on main domain
        if ($website->type === 'laravel1') {
            $base = $data['main_domain_url'] ?? ($website->ssl_enabled ? 'https://' : 'http://') . $mainDomain;
            $package = $this->getTemplatePackage($website);
            $scriptVersion = time();

            // Rewrite template asset paths to absolute URLs
            // Match: /templates/{package}/assets/*.css and /templates/{package}/assets/*.js
            $html = preg_replace(
                '#(href|src)="(/templates/[^/]+/assets/[^"?./]+\.(css|js))(\?[^"]*)?"#',
                '$1="' . $base . '$2?v=' . $scriptVersion . '"',
                $html
            );

            // Legacy fallback for old-style references (only if templateType is set)
            if (!empty($templateType)) {
                $html = preg_replace(
                    '#href="[^"]*style\.css[^"]*"#',
                    'href="' . $base . '/templates/' . $package . '/assets/' . $templateType . '.css?v=' . $scriptVersion . '"',
                    $html
                );
                $html = preg_replace(
                    '#src="[^"]*script\.js[^"]*"#',
                    'src="' . $base . '/templates/' . $package . '/assets/' . $templateType . '.js?v=' . $scriptVersion . '"',
                    $html
                );
            }
        }

        // Add script version for cache busting
        $html = str_replace('{{SCRIPT_VERSION}}', time(), $html);

        $html = $this->applyWebsiteSettingsHtml($html, $this->getMainSettings($page->website), $page->website);

        return $html;
    }

    /**
     * Deploy all assets from the template package
     */
    public function deployTemplatePackageAssets(Website $website): void
    {
        $vps = $website->vpsServer;
        if (!$vps || !$vps->isActive()) return;

        $package = $this->getTemplatePackage($website);
        $assetsDir = public_path("templates/{$package}/assets");

        if (!is_dir($assetsDir)) return;

        // Deploy all CSS and JS files from assets folder
        $files = glob($assetsDir . '/*.{css,js}', GLOB_BRACE);

        foreach ($files as $filePath) {
            $filename = basename($filePath);
            $content = file_get_contents($filePath);
            $response = null;

            try {
                $response = Http::timeout(30)
                    ->withHeaders(['X-Worker-Key' => $vps->worker_key, 'Content-Type' => 'application/json'])
                    ->post("http://{$vps->ip_address}:8080/api/deploy-page", [
                        'website_id' => $website->id,
                        'page_path' => "/templates/{$package}/assets",
                        'filename' => $filename,
                        'content' => $content,
                        'document_root' => $website->getDocumentRoot(),
                    ]);
            } catch (ConnectionException $e) {
                $response = Http::timeout(30)
                    ->withHeaders(['X-Worker-Key' => $vps->worker_key, 'Content-Type' => 'application/json'])
                    ->post("http://127.0.0.1:8080/api/deploy-page", [
                        'website_id' => $website->id,
                        'page_path' => "/templates/{$package}/assets",
                        'filename' => $filename,
                        'content' => $content,
                        'document_root' => $website->getDocumentRoot(),
                    ]);
            }

            if ($response && !$response->successful()) {
                Log::warning("Failed to deploy asset {$filename}", ['website_id' => $website->id, 'error' => $response->body()]);
            }
        }
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

    private function getMainSettings(Website $website): array
    {
        $parts = explode('.', $website->domain);
        $mainDomain = count($parts) > 2 ? implode('.', array_slice($parts, -2)) : $website->domain;
        $main = Website::where('domain', $mainDomain)->first();
        return $main ? ($main->custom_settings ?? []) : ($website->custom_settings ?? []);
    }

    private function applyWebsiteSettingsHtml(string $html, array $settings, Website $website): string
    {
        $domain = $website->domain;

        if (!empty($settings['title'])) {
            // Only replace {{TITLE}} placeholder if it exists, don't override already-set titles
            if (strpos($html, '{{TITLE}}') !== false) {
                $html = str_replace('{{TITLE}}', e($settings['title']), $html);
            }
        }
        if (!empty($settings['favicon_url']) && stripos($html, 'rel="icon"') === false) {
            if (preg_match('/<\/head>/i', $html)) {
                $localFaviconUrl = $this->convertToLocalAssetUrl('favicon_url', $settings['favicon_url'], $website);
                $html = preg_replace('/<\/head>/i', '<link rel="icon" href="' . e($localFaviconUrl) . '"></head>', $html, 1);
            }
        }
        if (!empty($settings['custom_head_html'])) {
            if (preg_match('/<\/head>/i', $html)) {
                $html = preg_replace('/<\/head>/i', $settings['custom_head_html'] . '</head>', $html, 1);
            }
        }
        if (!empty($settings['logo_header_url'])) {
            $localLogoUrl = $this->convertToLocalAssetUrl('logo_header_url', $settings['logo_header_url'], $website);
            $protocol = $website->ssl_enabled ? 'https://' : 'http://';
            $homeUrl = $protocol . $website->domain . '/';
            $html = preg_replace('/<h1[^>]*id=["\']site-name["\'][^>]*>[\s\S]*?<\/h1>/i', '<a href="' . e($homeUrl) . '"><img id="site-logo-header" src="' . e($localLogoUrl) . '" alt="' . e($settings['title'] ?? $domain) . '" class="h-8"></a>', $html, 1);
        } elseif (!empty($settings['title'])) {
            $protocol = $website->ssl_enabled ? 'https://' : 'http://';
            $homeUrl = $protocol . $website->domain . '/';
            $html = preg_replace('/<h1[^>]*id=["\']site-name["\'][^>]*>[\s\S]*?<\/h1>/i', '<a href="' . e($homeUrl) . '"><h1 id="site-name" class="text-2xl font-bold">' . e($settings['title']) . '</h1></a>', $html, 1);
        }
        if (!empty($settings['menu_html'])) {
            $html = preg_replace('/(<nav[^>]*>)[\s\S]*?(<\/nav>)/i', '$1' . $settings['menu_html'] . '$2', $html, 1);
        } elseif (!empty($settings['menu'])) {
            $protocol = 'https://';
            $base = $protocol . $domain;
            $menuHtml = $this->generateMenuHtml($settings['menu'], $base);
            if ($menuHtml) {
                $html = preg_replace('/(<nav[^>]*>)[\s\S]*?(<\/nav>)/i', '$1' . $menuHtml . '$2', $html, 1);
            }
        }
        if (!empty($settings['custom_body_html'])) {
            if (preg_match('/<body[^>]*>/i', $html)) {
                $html = preg_replace('/(<body[^>]*>)/i', '$1' . $settings['custom_body_html'], $html, 1);
            } else {
                $html = $settings['custom_body_html'] . $html;
            }
        }
        if (!empty($settings['logo_footer_url'])) {
            $localLogoFooterUrl = $this->convertToLocalAssetUrl('logo_footer_url', $settings['logo_footer_url'], $website);
            $html = preg_replace('/<h3[^>]*class=["\']text-xl[^>]*>[\s\S]*?<\/h3>/i', '<img id="site-logo-footer" src="' . e($localLogoFooterUrl) . '" alt="' . e($settings['title'] ?? $domain) . '" class="h-10">', $html, 1);
        } elseif (!empty($settings['title'])) {
            $html = preg_replace('/<h3[^>]*class=["\']text-xl[^>]*>[\s\S]*?<\/h3>/i', '<h3 class="text-xl font-bold mb-4">' . e($settings['title']) . '</h3>', $html, 1);
        }
        if (!empty($settings['footer_links_html']) || !empty($settings['custom_footer_html'])) {
            $injected = ($settings['footer_links_html'] ?? '') . ($settings['custom_footer_html'] ?? '');
            if (preg_match('/<footer[^>]*>[\s\S]*?<\/footer>/i', $html)) {
                $html = preg_replace('/(<footer[^>]*>[\s\S]*?)(<\/footer>)/i', '$1' . $injected . '$2', $html, 1);
            } elseif (stripos($html, '</body>') !== false) {
                $html = preg_replace('/<\/body>/i', $injected . '</body>', $html, 1);
            } else {
                $html .= $injected;
            }
        }
        return $html;
    }

    private function generateMenuHtml(array $menu, string $base): string
    {
        $build = function ($items) use (&$build, $base): string {
            $out = '<ul class="flex items-center gap-6">';
            foreach ($items as $it) {
                $label = (string)($it['label'] ?? '');
                $url = (string)($it['url'] ?? '');
                if ($url !== '' && str_starts_with($url, '/')) $url = rtrim($base, '/') . $url;
                $out .= '<li><a href="' . e($url ?: '#') . '" class="text-gray-700 hover:text-gray-900"><strong>' . e($label) . '</strong></a>';
                $children = $it['children'] ?? [];
                if (is_array($children) && count($children) > 0) {
                    $out .= '<ul class="ml-4 flex items-center gap-4">';
                    foreach ($children as $ch) {
                        $cl = (string)($ch['label'] ?? '');
                        $cu = (string)($ch['url'] ?? '');
                        if ($cu !== '' && str_starts_with($cu, '/')) $cu = rtrim($base, '/') . $cu;
                        $out .= '<li><a href="' . e($cu ?: '#') . '" class="text-gray-600 hover:text-gray-900">' . e($cl) . '</a></li>';
                    }
                    $out .= '</ul>';
                }
                $out .= '</li>';
            }
            $out .= '</ul>';
            return $out;
        };
        return $build($menu);
    }

    public function deployWebsiteAssets(Website $website): void
    {
        $vps = $website->vpsServer;
        if (!$vps || !$vps->isActive()) return;

        $settings = $this->getMainSettings($website);
        $docRoot = $website->getDocumentRoot();

        $items = [];

        // Logo header
        if (!empty($settings['logo_header_url'])) {
            // Try to get path from settings, or extract from URL
            $path = $settings['logo_header_path'] ?? null;
            if (!$path) {
                // Extract path from URL (e.g., http://localhost/uploads/example.com/assets/logo.png → /uploads/example.com/assets/logo.png)
                $parsed = parse_url($settings['logo_header_url']);
                $path = $parsed['path'] ?? null;
            }

            if ($path) {
                $src = public_path(ltrim($path, '/'));
                if (is_file($src) && filesize($src) > 0) {
                    $items[] = ['page_path' => '/assets', 'filename' => basename($src), 'content_base64' => base64_encode(file_get_contents($src))];
                }
            }
        }

        // Logo footer
        if (!empty($settings['logo_footer_url'])) {
            $path = $settings['logo_footer_path'] ?? null;
            if (!$path) {
                $parsed = parse_url($settings['logo_footer_url']);
                $path = $parsed['path'] ?? null;
            }

            if ($path) {
                $src = public_path(ltrim($path, '/'));
                if (is_file($src) && filesize($src) > 0) {
                    $items[] = ['page_path' => '/assets', 'filename' => basename($src), 'content_base64' => base64_encode(file_get_contents($src))];
                }
            }
        }

        // Favicon
        if (!empty($settings['favicon_url'])) {
            $path = $settings['favicon_path'] ?? null;
            if (!$path) {
                $parsed = parse_url($settings['favicon_url']);
                $path = $parsed['path'] ?? null;
            }

            if ($path) {
                $src = public_path(ltrim($path, '/'));
                if (is_file($src) && filesize($src) > 0) {
                    $items[] = ['page_path' => '/', 'filename' => basename($src), 'content_base64' => base64_encode(file_get_contents($src))];
                }
            }
        }

        foreach ($items as $it) {
            try {
                $resp = \Illuminate\Support\Facades\Http::timeout(60)
                    ->withHeaders([
                        'X-Worker-Key' => $vps->worker_key,
                        'Content-Type' => 'application/json',
                    ])
                    ->post("http://{$vps->ip_address}:8080/api/deploy-page", [
                        'website_id' => $website->id,
                        'page_path' => $it['page_path'],
                        'filename' => $it['filename'],
                        'content_base64' => $it['content_base64'],
                        'document_root' => $docRoot,
                    ]);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $resp = \Illuminate\Support\Facades\Http::timeout(60)
                    ->withHeaders([
                        'X-Worker-Key' => $vps->worker_key,
                        'Content-Type' => 'application/json',
                    ])
                    ->post("http://127.0.0.1:8080/api/deploy-page", [
                        'website_id' => $website->id,
                        'page_path' => $it['page_path'],
                        'filename' => $it['filename'],
                        'content_base64' => $it['content_base64'],
                        'document_root' => $docRoot,
                    ]);
            }
        }
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
