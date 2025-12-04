<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Website;
use App\Services\DeploymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PageController extends Controller
{
    private DeploymentService $deploymentService;

    public function __construct(DeploymentService $deploymentService)
    {
        $this->deploymentService = $deploymentService;
    }

    public function index(Website $website): JsonResponse
    {
        return response()->json($website->pages);
    }

    public function store(Request $request, Website $website): JsonResponse
    {
        $validated = $request->validate([
            'path' => 'required|string|max:255',
            'filename' => 'required|string|max:255',
            'title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'content' => 'nullable|string',
            'template_type' => 'nullable|in:blank,home,listing,detail,page',
            'template_data' => 'nullable|array',
            'folder_ids' => 'nullable|array',
            'folder_ids.*' => 'integer',
            'primary_folder_id' => 'nullable|integer',
        ]);

        // Validate path format
        if (!str_starts_with($validated['path'], '/')) {
            return response()->json(['error' => 'Path must start with /'], 422);
        }

        // Check if path already exists
        if ($website->pages()->where('path', $validated['path'])->exists()) {
            return response()->json(['error' => 'Path already exists'], 422);
        }

        $page = $website->pages()->create($validated);

        if (!empty($validated['folder_ids']) && is_array($validated['folder_ids'])) {
            $ids = collect($validated['folder_ids'])
                ->filter(fn($id) => is_int($id) || ctype_digit((string)$id))
                ->map(fn($id) => (int)$id)
                ->all();
            $page->folders()->sync($ids);
        }
        if (!empty($validated['primary_folder_id'])) {
            $page->primary_folder_id = (int) $validated['primary_folder_id'];
            $page->save();
        }

        $website->increment('content_version');
        $website->update(['content_updated_at' => now()]);

        // For subdomain pages, we need to deploy both the page and the main website
        $domainParts = explode('.', $website->domain);
        $isSubdomain = count($domainParts) > 2;

        if ($isSubdomain) {
            // Deploy the subdomain page itself
            $this->redeployLaravel1IfNeeded($website, $page);

            // Also deploy homepage and categories of the main website
            $mainDomain = implode('.', array_slice($domainParts, -2));
            $mainWebsite = Website::where('domain', $mainDomain)
                ->where('type', 'laravel1')
                ->where('status', 'deployed')
                ->first();

            if ($mainWebsite) {
                $this->redeployLaravel1IfNeeded($mainWebsite, null, $page->folders);
            }
        } else {
            $this->redeployLaravel1IfNeeded($website, $page);
        }

        return response()->json($page, 201);
    }

    public function show(Page $page): JsonResponse
    {
        return response()->json($page->load(['website', 'folders', 'primaryFolder']));
    }

    public function update(Request $request, Page $page): JsonResponse
    {
        $validated = $request->validate([
            'path' => 'sometimes|string|max:255',
            'filename' => 'sometimes|string|max:255',
            'title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'content' => 'sometimes|string',
            'template_type' => 'nullable|in:blank,home,listing,detail,page',
            'template_data' => 'nullable|array',
            'folder_ids' => 'nullable|array',
            'folder_ids.*' => 'integer',
            'primary_folder_id' => 'nullable|integer',
        ]);

        // Validate path format if provided
        if (isset($validated['path']) && !str_starts_with($validated['path'], '/')) {
            return response()->json(['error' => 'Path must start with /'], 422);
        }

        // Check if path already exists (excluding current page)
        if (isset($validated['path'])) {
            $existing = $page->website->pages()
                ->where('path', $validated['path'])
                ->where('id', '!=', $page->id)
                ->exists();

            if ($existing) {
                return response()->json(['error' => 'Path already exists'], 422);
            }
        }

        $oldPath = $page->path;
        $oldFilename = $page->filename;
        $pathChanged = isset($validated['path']) && $validated['path'] !== $oldPath;
        $filenameChanged = isset($validated['filename']) && $validated['filename'] !== $oldFilename;

        $page->update($validated);

        // Regenerate HTML content from template if template_type is set
        if (!empty($page->template_type) && $page->template_type !== 'blank') {
            $page->setRelation('website', $page->website);
            $page = $this->generatePageContent($page);
            $page->save();
        }

        if (array_key_exists('folder_ids', $validated)) {
            $ids = collect($validated['folder_ids'] ?? [])
                ->filter(fn($id) => is_int($id) || ctype_digit((string)$id))
                ->map(fn($id) => (int)$id)
                ->all();
            $page->folders()->sync($ids);
        }
        if (array_key_exists('primary_folder_id', $validated)) {
            $page->primary_folder_id = $validated['primary_folder_id'] ? (int) $validated['primary_folder_id'] : null;
            $page->save();
        }

        $page->website->increment('content_version');
        $page->website->update(['content_updated_at' => now()]);

        // For subdomain pages, we need to deploy both the page and the main website
        $website = $page->website;
        $domainParts = explode('.', $website->domain);
        $isSubdomain = count($domainParts) > 2;

        if ($isSubdomain) {
            // Deploy the subdomain page itself
            $this->redeployLaravel1IfNeeded(
                $website,
                $page,
                null,
                $pathChanged ? $oldPath : null,
                $filenameChanged ? $oldFilename : null
            );

            // Also deploy homepage and categories of the main website
            $mainDomain = implode('.', array_slice($domainParts, -2));
            $mainWebsite = Website::where('domain', $mainDomain)
                ->where('type', 'laravel1')
                ->where('status', 'deployed')
                ->first();

            if ($mainWebsite) {
                $this->redeployLaravel1IfNeeded($mainWebsite, null, $page->folders);
            }
        } else {
            $this->redeployLaravel1IfNeeded(
                $website,
                $page,
                null,
                $pathChanged ? $oldPath : null,
                $filenameChanged ? $oldFilename : null
            );
        }

        return response()->json($page);
    }

    public function destroy(Page $page): JsonResponse
    {
        $website = $page->website;
        $folders = $page->folders()->get();

        // If this is a subdomain page, we need to redeploy the main website
        $domainParts = explode('.', $website->domain);
        $isSubdomain = count($domainParts) > 2;

        if ($isSubdomain) {
            // Get main domain (e.g., timnhakhoa.com from luxeden-hanoi-hotel.timnhakhoa.com)
            $mainDomain = implode('.', array_slice($domainParts, -2));
            $mainWebsite = Website::where('domain', $mainDomain)
                ->where('type', 'laravel1')
                ->where('status', 'deployed')
                ->first();
        } else {
            $mainWebsite = $website;
        }

        $page->delete();

        $website->increment('content_version');
        $website->update(['content_updated_at' => now()]);

        // Deploy homepage and categories of the main website
        if ($mainWebsite) {
            $this->redeployLaravel1IfNeeded($mainWebsite, null, $folders);
        }

        return response()->json(null, 204);
    }

    private function redeployLaravel1IfNeeded(
        Website $website,
        ?Page $page = null,
        $folders = null,
        ?string $oldPath = null,
        ?string $oldFilename = null
    ): void {
        if ($website->type !== 'laravel1' || $website->status !== 'deployed') {
            return;
        }

        // Check if this is a subdomain
        $domainParts = explode('.', $website->domain);
        $isSubdomain = count($domainParts) > 2;

        // Deploy the specific page if provided
        if ($page) {
            \App\Jobs\DeployLaravel1Page::dispatch($page->id, $oldPath, $oldFilename);
        }

        // For main domain, also deploy homepage and category pages
        if (!$isSubdomain) {
            \App\Jobs\DeployLaravel1Homepage::dispatch($website->id);

            // Redeploy affected category pages
            $affectedFolders = $folders ?? ($page ? $page->folders()->get() : collect());
            foreach ($affectedFolders as $folder) {
                \App\Jobs\DeployLaravel1CategoryPage::dispatch($folder->id);
            }
        }
    }

    private function generatePageContent(Page $page): Page
    {
        // If template_type is blank or no template_data, keep content as is
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
            // Template file doesn't exist, keep content empty
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

    /**
     * Parse rating from text or number
     * Supports formats like "3 out of 5 stars", "4.5/5", or just "4"
     */
    private function parseRating($rating): float
    {
        if (is_numeric($rating)) {
            return (float) $rating;
        }

        if (is_string($rating)) {
            // Handle "3 out of 5 stars" format
            if (preg_match('/(\d+(?:\.\d+)?)\s*out\s*of\s*5/i', $rating, $matches)) {
                return (float) $matches[1];
            }
            // Handle "4.5/5" format
            if (preg_match('/(\d+(?:\.\d+)?)\s*\/\s*5/i', $rating, $matches)) {
                return (float) $matches[1];
            }
            // Try to extract any number
            if (preg_match('/(\d+(?:\.\d+)?)/', $rating, $matches)) {
                return (float) $matches[1];
            }
        }

        return 0;
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
                    }, array_slice($item['houseRules'] ?? [], 0, 10)),
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

    public function import(Request $request, Website $website): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'required|array',
            'data.*.name' => 'required|string',
            'data.*.path' => 'nullable|string',
            'data.*.address' => 'nullable|string',
            'data.*.rating' => 'nullable|numeric',
            'data.*.ratingCategory' => 'nullable|string',
            'data.*.reviewCount' => 'nullable|integer',
            'data.*.images' => 'nullable|array',
            'data.*.facilities' => 'nullable|array',
            'data.*.faqs' => 'nullable|array',
            'data.*.about' => 'nullable|string',
            'data.*.houseRules' => 'nullable|array',
            'data.*.content' => 'nullable|string',
            'data.*.services' => 'nullable|array',
            'data.*.testimonials' => 'nullable|array',
            'data.*.items' => 'nullable|array',
            'data.*.filters' => 'nullable|array',
            'folder_ids' => 'nullable|array',
            'folder_ids.*' => 'integer',
            'template_type' => 'nullable|string|in:detail,blank,home,listing',
        ]);

        $items = $validated['data'];
        $folderIds = $validated['folder_ids'] ?? [];
        $templateType = $validated['template_type'] ?? 'detail';

        $stats = [
            'total' => count($items),
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => []
        ];

        foreach ($items as $index => $item) {
            try {
                $title = $item['name'];

                // Check if page with this title already exists
                $existingPage = $website->pages()->where('title', $title)->first();

                // Map JSON data to template_data format based on template type
                $templateData = $this->buildTemplateData($templateType, $item, $title);

                if ($existingPage) {
                    // Update existing page
                    $existingPage->update([
                        'template_type' => $templateType,
                        'template_data' => $templateData
                    ]);

                    // Explicitly set the website relationship
                    $existingPage->setRelation('website', $website);

                    // Generate HTML content from template
                    $existingPage = $this->generatePageContent($existingPage);
                    $existingPage->save();

                    // Update folder associations if provided
                    if (!empty($folderIds)) {
                        $existingPage->folders()->sync($folderIds);
                    }

                    $stats['updated']++;
                } else {
                    // Create new page
                    // Use custom path if provided, otherwise generate from title
                    if (isset($item['path']) && !empty($item['path'])) {
                        $path = $item['path'];
                    } else {
                        // Generate slug from title
                        $slug = \Illuminate\Support\Str::slug($title);
                        $path = '/' . $slug;

                        // Check if path exists, add number if needed
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
                        'template_type' => $templateType,
                        'template_data' => $templateData,
                        'content' => '' // Will be generated below
                    ]);

                    // Explicitly set the website relationship
                    $page->setRelation('website', $website);

                    // Generate HTML content from template
                    $page = $this->generatePageContent($page);
                    $page->save();

                    // Attach folders if provided
                    if (!empty($folderIds)) {
                        $page->folders()->attach($folderIds);
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
            }
        }

        return response()->json([
            'message' => 'Import completed',
            'stats' => $stats
        ]);
    }
}
