<?php

namespace App\Console\Commands;

use App\Models\Page;
use Illuminate\Console\Command;

class RegeneratePages extends Command
{
    protected $signature = 'pages:regenerate {--website_id=}';
    protected $description = 'Regenerate HTML content for all template-based pages';

    public function handle()
    {
        $websiteId = $this->option('website_id');

        $query = Page::whereNotNull('template_type')
            ->where('template_type', '!=', 'blank');

        if ($websiteId) {
            $query->where('website_id', $websiteId);
        }

        $pages = $query->with('website')->get();

        $this->info("Found {$pages->count()} pages to regenerate");

        $bar = $this->output->createProgressBar($pages->count());
        $bar->start();

        foreach ($pages as $page) {
            try {
                $page = $this->generatePageContent($page);
                $page->save();
                $bar->advance();
            } catch (\Exception $e) {
                $this->error("\nFailed to regenerate page {$page->id}: " . $e->getMessage());
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info('Done!');
    }

    private function generatePageContent(Page $page): Page
    {
        if ($page->template_type === 'blank' || empty($page->template_data)) {
            return $page;
        }

        $website = $page->website;
        $templateType = $page->template_type;
        $templateData = $page->template_data;

        // Get template package
        $package = $website->template_package ?? 'laravel-hotel-1';
        $templatePath = public_path("templates/{$package}/{$templateType}/index.html");

        // Load template HTML
        if (!file_exists($templatePath)) {
            $this->warn("Template not found: {$templatePath}");
            return $page;
        }

        $html = file_get_contents($templatePath);

        // Load shared header and footer
        $sharedHeaderPath = public_path("templates/{$package}/shared/header.html");
        $sharedFooterPath = public_path("templates/{$package}/shared/footer.html");

        $sharedHeader = file_exists($sharedHeaderPath) ? file_get_contents($sharedHeaderPath) : '';
        $sharedFooter = file_exists($sharedFooterPath) ? file_get_contents($sharedFooterPath) : '';

        // Inject shared header before <body>
        if ($sharedHeader) {
            if (preg_match('/<body[^>]*>/i', $html)) {
                $html = preg_replace('/(<body[^>]*>)/i', $sharedHeader . '$1', $html, 1);
            } else {
                $html = $sharedHeader . $html;
            }
        }

        // Inject shared footer before closing </body> or at end
        if ($sharedFooter) {
            if (preg_match('/<\/body>/i', $html)) {
                $html = preg_replace('/<\/body>/i', $sharedFooter . '</body>', $html, 1);
            } else {
                $html .= $sharedFooter;
            }
        }

        // Wrap with <!DOCTYPE html> and <html> if not present
        if (!preg_match('/<!DOCTYPE/i', $html)) {
            $html = "<!DOCTYPE html>\n<html lang=\"en\">\n" . $html . "\n</html>";
        }

        // Inject template data and replace placeholders
        $html = $this->injectTemplateData($html, $templateData, $page);

        $page->content = $html;
        return $page;
    }

    private function injectTemplateData(string $html, array $data, Page $page): string
    {
        // Get protocol and domain info
        $protocol = $page->website->ssl_enabled ? 'https://' : 'http://';
        $domainParts = explode('.', $page->website->domain);
        $rootDomain = count($domainParts) > 2 ? implode('.', array_slice($domainParts, -2)) : $page->website->domain;
        $assetBaseUrl = $protocol . $rootDomain;

        // Add missing fields that detail.js expects
        $data['main_domain_url'] = $assetBaseUrl;

        // Build breadcrumb paths
        $breadcrumbItems = $data['breadcrumb_items'] ?? ['Home', 'Stays', $data['title'] ?? $page->title];
        $breadcrumbPaths = ['/', '/', '']; // Home, Stays category (placeholder), current page (no link)

        // Get folder hierarchy if page has folders
        $folder = $page->folders()->first();
        if ($folder) {
            $breadcrumbItems = ['Home'];
            $breadcrumbPaths = ['/'];

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
        }

        $data['breadcrumb_items'] = $breadcrumbItems;
        $data['breadcrumb_paths'] = $breadcrumbPaths;

        // Inject data as JSON script tag (detail.js reads from this)
        $dataScript = '<script type="application/json" id="page-data">' . json_encode($data, JSON_UNESCAPED_UNICODE) . '</script>';
        $html = str_replace('{{GALLERY_DATA_SCRIPT}}', $dataScript, $html);

        // Inject SCRIPT_VERSION
        $html = str_replace('{{SCRIPT_VERSION}}', time(), $html);

        // Inject meta tags
        $title = $data['title'] ?? $page->title ?? 'Page';
        $description = $data['about1'] ?? $data['about'] ?? '';
        if (strlen($description) > 160) {
            $description = substr($description, 0, 157) . '...';
        }

        $ogImage = '';
        if (!empty($data['gallery']) && is_array($data['gallery']) && count($data['gallery']) > 0) {
            $ogImage = $data['gallery'][0];
        }

        $ogUrl = $protocol . $page->website->domain . $page->path;

        $html = str_replace('{{TITLE}}', htmlspecialchars($title, ENT_QUOTES, 'UTF-8'), $html);
        $html = str_replace('{{DESCRIPTION}}', htmlspecialchars($description, ENT_QUOTES, 'UTF-8'), $html);
        $html = str_replace('{{OG_IMAGE}}', htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8'), $html);
        $html = str_replace('{{OG_URL}}', htmlspecialchars($ogUrl, ENT_QUOTES, 'UTF-8'), $html);

        // Convert relative script and link URLs to absolute URLs pointing to root domain
        $html = preg_replace(
            '/<script([^>]*?)\s+src=["\'](\/[^"\']+)["\']/',
            '<script$1 src="' . $assetBaseUrl . '$2"',
            $html
        );
        $html = preg_replace(
            '/<link([^>]*?)\s+href=["\'](\/[^"\']+)["\']/',
            '<link$1 href="' . $assetBaseUrl . '$2"',
            $html
        );

        return $html;
    }
}
