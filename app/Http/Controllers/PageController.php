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

    public function import(Request $request, Website $website): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'required|array',
            'data.*.name' => 'required|string',
            'data.*.address' => 'nullable|string',
            'data.*.rating' => 'nullable|numeric',
            'data.*.ratingCategory' => 'nullable|string',
            'data.*.reviewCount' => 'nullable|integer',
            'data.*.images' => 'nullable|array',
            'data.*.facilities' => 'nullable|array',
            'data.*.faqs' => 'nullable|array',
            'data.*.about' => 'nullable|string',
            'data.*.houseRules' => 'nullable|array',
            'folder_ids' => 'nullable|array',
            'folder_ids.*' => 'integer',
        ]);

        $items = $validated['data'];
        $folderIds = $validated['folder_ids'] ?? [];

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

                // Map JSON data to template_data format
                $templateData = [
                    'title' => $title,
                    'location' => $item['address'] ?? '',
                    'location_text' => $item['address'] ?? '',
                    'phone' => '',
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

                if ($existingPage) {
                    // Update existing page
                    $existingPage->update([
                        'template_type' => 'detail',
                        'template_data' => $templateData
                    ]);

                    // Update folder associations if provided
                    if (!empty($folderIds)) {
                        $existingPage->folders()->sync($folderIds);
                    }

                    $stats['updated']++;
                } else {
                    // Create new page
                    // Generate slug from title
                    $slug = \Illuminate\Support\Str::slug($title);
                    $path = '/' . $slug;

                    // Check if path exists, add number if needed
                    $counter = 1;
                    while ($website->pages()->where('path', $path)->exists()) {
                        $path = '/' . $slug . '-' . $counter;
                        $counter++;
                    }

                    $page = $website->pages()->create([
                        'path' => $path,
                        'filename' => 'index.html',
                        'title' => $title,
                        'template_type' => 'detail',
                        'template_data' => $templateData,
                        'content' => '' // Will be generated during deployment
                    ]);

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
