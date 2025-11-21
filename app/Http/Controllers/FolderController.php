<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Models\Folder;
use App\Services\DeploymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class FolderController extends Controller
{
    private DeploymentService $deploymentService;

    public function __construct(DeploymentService $deploymentService)
    {
        $this->deploymentService = $deploymentService;
    }
    public function index(Website $website): JsonResponse
    {
        $root = $this->rootWebsiteFor($website);
        $folders = Folder::where('website_id', $root->id)
            ->withCount(['pages as pages_count' => function ($q) use ($root) {
                $q->where('website_id', $root->id);
            }])
            ->orderBy('name')
            ->get();
        return response()->json($folders);
    }

    public function store(Request $request, Website $website): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|integer',
        ]);

        $root = $this->rootWebsiteFor($website);
        $parentId = $validated['parent_id'] ?? null;
        if ($parentId) {
            $parent = Folder::where('id', (int)$parentId)->where('website_id', $root->id)->first();
            if (!$parent) {
                return response()->json(['error' => 'Invalid parent folder'], 422);
            }
        }

        $base = Str::slug($validated['slug'] ?? $validated['name']);
        $slug = $base;
        $i = 2;
        while (Folder::where('website_id', $root->id)->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        $folder = new Folder([
            'website_id' => $root->id,
            'parent_id' => $parentId ? (int)$parentId : null,
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
        ]);
        $folder->save();

        $this->redeployLaravel1IfNeeded($root);

        return response()->json($folder, 201);
    }

    public function update(Request $request, Website $website, Folder $folder): JsonResponse
    {
        $root = $this->rootWebsiteFor($website);
        if ($folder->website_id !== $root->id) {
            return response()->json(['error' => 'Folder not in website'], 404);
        }
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|integer',
        ]);

        $parentId = array_key_exists('parent_id', $validated) ? $validated['parent_id'] : $folder->parent_id;
        if ($parentId) {
            $parent = Folder::where('id', (int)$parentId)->where('website_id', $root->id)->first();
            if (!$parent) {
                return response()->json(['error' => 'Invalid parent folder'], 422);
            }
            // prevent cyclic parent
            if ($parent->id === $folder->id) {
                return response()->json(['error' => 'Parent cannot be itself'], 422);
            }
        }

        $data = [
            'name' => $validated['name'] ?? $folder->name,
            'description' => $validated['description'] ?? $folder->description,
            'parent_id' => $parentId ? (int)$parentId : null,
        ];

        // handle slug uniqueness (if provided or name changed)
        $base = Str::slug($validated['slug'] ?? $data['name']);
        $slug = $base;
        $i = 2;
        while (Folder::where('website_id', $root->id)->where('slug', $slug)->where('id', '!=', $folder->id)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }
        $data['slug'] = $slug;

        $folder->name = $data['name'];
        $folder->description = $data['description'];
        $folder->parent_id = $data['parent_id'];
        $folder->slug = $data['slug'];
        $folder->save();

        $this->redeployLaravel1IfNeeded($root, $folder);

        return response()->json($folder);
    }

    public function destroy(Website $website, Folder $folder): JsonResponse
    {
        $root = $this->rootWebsiteFor($website);
        if ($folder->website_id !== $root->id) {
            return response()->json(['error' => 'Folder not in website'], 404);
        }
        // prevent delete if has children
        $hasChildren = Folder::where('parent_id', $folder->id)->exists();
        if ($hasChildren) {
            return response()->json(['error' => 'Folder has child folders'], 422);
        }
        // detach pages
        $folder->pages()->detach();
        $folder->delete();

        $this->redeployLaravel1IfNeeded($root);

        return response()->json(null, 204);
    }

    private function redeployLaravel1IfNeeded(Website $website, ?Folder $folder = null): void
    {
        if ($website->type !== 'laravel1' || $website->status !== 'deployed') {
            return;
        }

        try {
            $this->deploymentService->deployLaravel1Homepage($website);
            if ($folder) {
                $this->deploymentService->deployLaravel1CategoryPage($folder);
            }
        } catch (\Exception $e) {
            // Silently fail - don't block the main operation
        }
    }

    private function rootWebsiteFor(Website $website): Website
    {
        $domain = trim($website->domain ?? '');
        $parts = array_values(array_filter(explode('.', $domain)));
        if (count($parts) >= 2) {
            $rootDomain = $parts[count($parts)-2] . '.' . $parts[count($parts)-1];
            $found = Website::where('domain', $rootDomain)->first();
            if ($found) return $found;
        }
        return $website;
    }
}