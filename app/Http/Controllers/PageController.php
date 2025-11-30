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

    private function redeployLaravel1IfNeeded(Website $website, ?Page $page = null, $folders = null): void
    {
        if ($website->type !== 'laravel1' || $website->status !== 'deployed') {
            return;
        }

        // Check if this is a subdomain - don't deploy homepage for subdomains
        $domainParts = explode('.', $website->domain);
        $isSubdomain = count($domainParts) > 2;

        // For subdomains, deploy the page itself
        if ($isSubdomain && $page) {
            $pending = dispatch(function () use ($page) {
                app(\App\Services\DeploymentService::class)->deployPage($page);
            });
            if (method_exists($pending, 'afterResponse')) { $pending->afterResponse(); }
            return;
        }

        // Only deploy homepage for main domain, not subdomains
        if (!$isSubdomain) {
            \App\Jobs\DeployLaravel1Homepage::dispatch($website->id);

            // Redeploy affected category pages
            $affectedFolders = $folders ?? ($page ? $page->folders()->get() : collect());
            foreach ($affectedFolders as $folder) {
                \App\Jobs\DeployLaravel1CategoryPage::dispatch($folder->id);
            }
        }
    }
}
