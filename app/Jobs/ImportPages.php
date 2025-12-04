<?php

namespace App\Jobs;

use App\Models\Website;
use App\Models\Page;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ImportPages implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $websiteId,
        public array $items,
        public array $folderIds,
        public string $templateType
    ) {}

    public function handle(): void
    {
        $website = Website::find($this->websiteId);
        if (!$website) {
            Log::warning('ImportPages: Website not found', ['website_id' => $this->websiteId]);
            return;
        }

        $stats = [
            'total' => count($this->items),
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => []
        ];

        Log::info('ImportPages: Starting', [
            'website_id' => $this->websiteId,
            'total_items' => count($this->items)
        ]);

        foreach ($this->items as $index => $item) {
            try {
                $title = $item['name'];

                // Check if page with this title already exists
                $existingPage = $website->pages()->where('title', $title)->first();

                // Map JSON data to template_data format
                $templateData = $this->buildTemplateData($this->templateType, $item, $title);

                if ($existingPage) {
                    // Update existing page
                    $existingPage->update([
                        'template_type' => $this->templateType,
                        'template_data' => $templateData
                    ]);

                    $existingPage->setRelation('website', $website);
                    $existingPage = $this->generatePageContent($existingPage);
                    $existingPage->save();

                    if (!empty($this->folderIds)) {
                        $existingPage->folders()->sync($this->folderIds);
                    }

                    $stats['updated']++;
                } else {
                    // Create new page
                    if (isset($item['path']) && !empty($item['path'])) {
                        $path = $item['path'];
                    } else {
                        $slug = \Illuminate\Support\Str::slug($title);
                        $path = '/' . $slug;

                        $counter = 1;
                        while ($website->pages()->where('path', $path)->exists()) {
                            $path = '/' . $slug . '-' . $counter;
                            $counter++;
                        }
                    }

                    $page = $website->pages()->create([
                        'path' => $path,
                        'filename' => 'index.html',
                        'title' => $title,
                        'template_type' => $this->templateType,
                        'template_data' => $templateData,
                        'content' => ''
                    ]);

                    $page->setRelation('website', $website);
                    $page = $this->generatePageContent($page);
                    $page->save();

                    if (!empty($this->folderIds)) {
                        $page->folders()->attach($this->folderIds);
                    }

                    $stats['created']++;
                }
            } catch (\Exception $e) {
                $stats['skipped']++;
                $stats['errors'][] = [
                    'index' => $index,
                    'title' => $item['name'] ?? 'Unknown',
                    'error' => $e->getMessage()
                ];
                Log::error('ImportPages: Failed to import item', [
                    'website_id' => $this->websiteId,
                    'index' => $index,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('ImportPages: Completed', array_merge(['website_id' => $this->websiteId], $stats));
    }

    private function generatePageContent(Page $page): Page
    {
        if ($page->template_type === 'blank' || empty($page->template_data)) {
            return $page;
        }

        $website = $page->website;
        $templateType = $page->template_type;
        $templateData = $page->template_data;

        $package = $website->template_package ?? 'laravel-hotel-1';
        $templatePath = public_path("templates/{$package}/{$templateType}/index.html");

        if (!file_exists($templatePath)) {
            return $page;
        }

        $html = file_get_contents($templatePath);

        // Load shared header and footer
        $headerPath = public_path("templates/{$package}/shared/header.html");
        $footerPath = public_path("templates/{$package}/shared/footer.html");

        $sharedHeader = file_exists($headerPath) ? file_get_contents($headerPath) : '';
        $sharedFooter = file_exists($footerPath) ? file_get_contents($footerPath) : '';

        $html = $sharedHeader . $html . $sharedFooter;

        if (!preg_match('/<!DOCTYPE/i', $html)) {
            $html = "<!DOCTYPE html>\n<html lang=\"en\">\n" . $html . "\n</html>";
        }

        $html = $this->injectTemplateData($html, $templateData, $page);

        $page->content = $html;
        return $page;
    }

    private function injectTemplateData(string $html, array $data, Page $page): string
    {
        $protocol = $page->website->ssl_enabled ? 'https://' : 'http://';
        $domainParts = explode('.', $page->website->domain);
        $rootDomain = count($domainParts) > 2 ? implode('.', array_slice($domainParts, -2)) : $page->website->domain;
        $assetBaseUrl = $protocol . $rootDomain;

        $data['main_domain_url'] = $assetBaseUrl;

        $breadcrumbItems = $data['breadcrumb_items'] ?? ['Home', 'Stays', $data['title'] ?? $page->title];
        $breadcrumbPaths = ['/', '/', ''];

        $folder = $page->folders()->first();
        if ($folder) {
            $breadcrumbItems = ['Home'];
            $breadcrumbPaths = ['/'];

            $folderHierarchy = [];
            $currentFolder = $folder;
            while ($currentFolder) {
                array_unshift($folderHierarchy, $currentFolder);
                $currentFolder = $currentFolder->parent;
            }

            foreach ($folderHierarchy as $f) {
                $breadcrumbItems[] = $f->name;
                $breadcrumbPaths[] = $f->getPath();
            }

            $breadcrumbItems[] = $data['title'] ?? $page->title ?? 'Untitled';
            $breadcrumbPaths[] = '';
        }

        $data['breadcrumb_items'] = $breadcrumbItems;
        $data['breadcrumb_paths'] = $breadcrumbPaths;

        $dataScript = '<script type="application/json" id="page-data">' . json_encode($data, JSON_UNESCAPED_UNICODE) . '</script>';
        $html = str_replace('{{GALLERY_DATA_SCRIPT}}', $dataScript, $html);

        $html = str_replace('{{SCRIPT_VERSION}}', time(), $html);

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

    private function parseRating($rating): float
    {
        if (is_numeric($rating)) {
            return (float) $rating;
        }

        if (is_string($rating)) {
            if (preg_match('/(\d+(?:\.\d+)?)\s*out\s*of\s*5/i', $rating, $matches)) {
                return (float) $matches[1];
            }
            if (preg_match('/(\d+(?:\.\d+)?)\s*\/\s*5/i', $rating, $matches)) {
                return (float) $matches[1];
            }
            if (preg_match('/(\d+(?:\.\d+)?)/', $rating, $matches)) {
                return (float) $matches[1];
            }
        }

        return 0;
    }

    private function normalizeHouseRules($houseRules): array
    {
        if (!$houseRules) {
            return [];
        }

        if (is_array($houseRules) && isset($houseRules[0])) {
            return $houseRules;
        }

        if (is_array($houseRules)) {
            $result = [];
            foreach ($houseRules as $key => $value) {
                $title = ucfirst(preg_replace('/([A-Z])/', ' $1', $key));
                $result[] = [
                    'title' => $title,
                    'description' => $value
                ];
            }
            return $result;
        }

        return [];
    }

    private function buildTemplateData(string $templateType, array $item, string $title): array
    {
        switch ($templateType) {
            case 'detail':
                return [
                    'title' => $title,
                    'location' => $item['address'] ?? '',
                    'location_text' => $item['address'] ?? '',
                    'phone' => '',
                    'rating' => $this->parseRating($item['rating'] ?? null),
                    'about1' => $item['about'] ?? '',
                    'amenities' => $item['facilities'] ?? [],
                    'faqs' => array_map(function($faq) {
                        return [
                            'q' => $faq['question'] ?? '',
                            'a' => $faq['answer'] ?? ''
                        ];
                    }, $item['faqs'] ?? []),
                    'info' => array_map(function($rule) {
                        return [
                            'subject' => is_string($rule) ? $rule : ($rule['title'] ?? ''),
                            'description' => is_string($rule) ? '' : ($rule['description'] ?? '')
                        ];
                    }, array_slice($this->normalizeHouseRules($item['houseRules'] ?? []), 0, 10)),
                    'gallery' => array_slice($item['images'] ?? [], 0, 50),
                    'breadcrumb_items' => ['Home', 'Stays', $title]
                ];

            case 'blank':
                return [
                    'title' => $title,
                    'content' => $item['content'] ?? $item['about'] ?? ''
                ];

            case 'home':
                return [
                    'title' => $title,
                    'about' => $item['about'] ?? '',
                    'services' => $item['services'] ?? [],
                    'testimonials' => $item['testimonials'] ?? []
                ];

            case 'listing':
                return [
                    'title' => $title,
                    'items' => $item['items'] ?? [],
                    'filters' => $item['filters'] ?? []
                ];

            default:
                return ['title' => $title];
        }
    }
}
