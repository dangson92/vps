<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Models\VpsServer;
use App\Services\DeploymentService;
use App\Services\DnsService;
use App\Services\SslService;
use App\Services\MonitoringService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class WebsiteController extends Controller
{
    private DeploymentService $deploymentService;
    private SslService $sslService;
    private MonitoringService $monitoringService;

    public function __construct(
        DeploymentService $deploymentService,
        SslService $sslService,
        MonitoringService $monitoringService
    ) {
        $this->deploymentService = $deploymentService;
        $this->sslService = $sslService;
        $this->monitoringService = $monitoringService;
    }

    public function index(): JsonResponse
    {
        $websites = Website::with(['vpsServer', 'pages'])->get();
        return response()->json($websites);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => 'required|string|max:255|unique:websites',
            'type' => 'required|in:html,wordpress,laravel1',
            'template_package' => 'required_if:type,laravel1|string|in:laravel-hotel-1',
            'vps_server_id' => 'required|exists:vps_servers,id',
            'cloudflare_account_id' => 'nullable|exists:cloudflare_accounts,id',
            'wordpress_template' => 'required_if:type,wordpress|string|max:255',
        ]);

        $vps = VpsServer::findOrFail($validated['vps_server_id']);

        if (!$vps->isActive()) {
            return response()->json(['error' => 'VPS server is not active'], 422);
        }

        $website = new Website([
            'domain' => $validated['domain'],
            'type' => $validated['type'],
            'template_package' => $validated['template_package'] ?? null,
            'vps_server_id' => $validated['vps_server_id'],
            'status' => 'draft',
            'content_version' => 1,
            'deployed_version' => 0,
        ]);

        if ($validated['type'] === 'wordpress') {
            $website->wordpress_config = [
                'template' => $validated['wordpress_template'],
                'db_name' => 'wp_' . Str::snake($validated['domain']),
                'db_user' => 'wp_' . Str::snake($validated['domain']),
                'db_password' => Str::random(16),
            ];
        }

        $website->save();

        // Create default pages for HTML websites
        if ($validated['type'] === 'html') {
            $this->createDefaultPages($website);
        }

        return response()->json($website->load('vpsServer'), 201);
    }

    public function show(Website $website): JsonResponse
    {
        return response()->json($website->load(['vpsServer', 'pages', 'dnsRecords']));
    }

    public function update(Request $request, Website $website): JsonResponse
    {
        $validated = $request->validate([
            'ssl_enabled' => 'sometimes|boolean',
            'status' => 'sometimes|in:pending,deploying,deployed,error',
        ]);

        $website->update($validated);

        return response()->json($website);
    }

    public function destroy(Website $website): JsonResponse
    {
        // Remove from VPS
        $this->deploymentService->removeWebsite($website);

        // Delete DNS records
        $dnsService = new DnsService($website);
        $dnsService->deleteWebsiteRecords($website);

        // Delete monitoring stats (FK constraint)
        try {
            $website->monitoringStats()->delete();
        } catch (\Throwable $e) {
        }

        // Delete pages before deleting website to satisfy FK constraints
        $website->pages()->delete();

        $website->delete();

        return response()->json(null, 204);
    }

    public function deploy(Website $website): JsonResponse
    {
        if ($website->status === 'deploying') {
            return response()->json(['error' => 'Website is already being deployed'], 422);
        }

        $website->update(['status' => 'deploying']);

        try {
            $this->deploymentService->deploy($website);
            $this->deploymentService->publishAllPages($website);
            
            // Create DNS records
            $dnsService = new DnsService($website);
            $dnsService->createRecords($website);
            
            // Generate SSL if enabled
            if ($website->ssl_enabled) {
                $this->sslService->generate($website);
            }

            $website->update([
                'status' => 'deployed',
                'deployed_at' => now(),
                'deployed_version' => $website->content_version,
            ]);
            $this->monitoringService->checkUptime($website);

            return response()->json([
                'message' => 'Website deployed successfully',
                'website' => $website->fresh()
            ]);
        } catch (\Throwable $e) {
            $website->update(['status' => 'error']);
            
            return response()->json([
                'error' => 'Deployment failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function deactivate(Website $website): JsonResponse
    {
        try {
            $this->deploymentService->deactivateWebsite($website);
        } catch (\Exception $e) {
        }

        $website->update([
            'status' => 'suspended',
            'suspended_at' => now(),
        ]);

        return response()->json([
            'message' => 'Website deactivated',
            'website' => $website->fresh(),
        ]);
    }

    /**
     * Redeploy all pages for a website and its subdomains
     */
    public function redeployPages(Website $website): JsonResponse
    {
        $domainPattern = "%." . $website->domain;
        $websites = Website::where(function($q) use ($website, $domainPattern) {
            $q->where('domain', $website->domain)
              ->orWhere('domain', 'like', $domainPattern);
        })->where('type', 'laravel1')
          ->where('status', 'deployed')
          ->get();

        $queued = 0;
        foreach ($websites as $site) {
            foreach ($site->pages as $page) {
                $pending = dispatch(function () use ($page) {
                    app(\App\Services\DeploymentService::class)->deployPage($page);
                });
                if (method_exists($pending, 'afterResponse')) { $pending->afterResponse(); }
                $queued++;
            }
        }

        $domainParts = explode('.', $website->domain);
        $mainDomain = count($domainParts) > 2 ? implode('.', array_slice($domainParts, -2)) : $website->domain;
        $mainWebsite = Website::where('domain', $mainDomain)
            ->where('type', 'laravel1')
            ->where('status', 'deployed')
            ->first();
        $homeQueued = 0;
        $catQueued = 0;
        if ($mainWebsite) {
            $pHome = \App\Jobs\DeployLaravel1Homepage::dispatch($mainWebsite->id);
            if (method_exists($pHome, 'afterResponse')) { $pHome->afterResponse(); }
            $homeQueued = 1;
            $folders = \App\Models\Folder::where('website_id', $mainWebsite->id)->get();
            foreach ($folders as $folder) {
                $pCat = \App\Jobs\DeployLaravel1CategoryPage::dispatch($folder->id);
                if (method_exists($pCat, 'afterResponse')) { $pCat->afterResponse(); }
                $catQueued++;
            }
        }

        return response()->json([
            'message' => "Queued redeploy for {$queued} pages" . ($homeQueued ? "; homepage queued" : "") . ($catQueued ? "; {$catQueued} categories queued" : ""),
            'queued' => $queued
        ]);
    }

    /**
     * Redeploy template assets (CSS, JS) from template package
     */
    public function redeployTemplateAssets(Request $request, Website $website): JsonResponse
    {
        $validated = $request->validate([
            'template_names' => 'nullable|array',
            'template_names.*' => 'string|in:home,listing,detail,all',
            'refresh_pages' => 'sometimes|boolean',
        ]);

        $refresh = filter_var($request->input('refresh_pages', false), FILTER_VALIDATE_BOOLEAN);

        // Redeploy all package assets
        $pending = \App\Jobs\RedeployTemplateAssets::dispatch($website->id, $refresh);
        if (method_exists($pending, 'afterResponse')) {
            $pending->afterResponse();
        }

        return response()->json([
            'message' => 'Template package asset redeploy queued',
            'package' => $website->template_package ?? 'laravel-hotel-1',
            'refresh_pages' => $refresh,
        ]);
    }

    /**
     * Update page HTML from latest template package (header/footer sync)
     */
    public function updatePagesTemplate(Request $request, Website $website): JsonResponse
    {
        $validated = $request->validate([
            'template_names' => 'nullable|array',
            'template_names.*' => 'string|in:home,listing,detail,all',
            'page_ids' => 'nullable|array',
            'page_ids.*' => 'integer|exists:pages,id'
        ]);

        try {
            $templateNames = $validated['template_names'] ?? null;
            $applyAll = !$templateNames || in_array('all', $templateNames, true);
            $templateFilters = $applyAll ? null : array_values(array_unique(array_filter($templateNames)));

            $updatedCount = 0;
            $skippedCount = 0;

            $pageIds = $validated['page_ids'] ?? [];
            $knownTemplates = ['home', 'listing', 'detail'];
            $normalizedFilters = $templateFilters;
            if (!empty($pageIds)) {
                $pages = \App\Models\Page::whereIn('id', $pageIds)->get();
            } else {
                // Get all subdomains
                $domainPattern = "%." . $website->domain;
                $websites = Website::where(function($q) use ($website, $domainPattern) {
                    $q->where('domain', $website->domain)
                      ->orWhere('domain', 'like', $domainPattern);
                })->where('type', 'laravel1')
                  ->where('status', 'deployed')
                  ->get();
                $pages = collect();
                foreach ($websites as $site) {
                    $pages = $pages->merge($site->pages);
                }
            }

            foreach ($pages as $page) {
                $pageTpl = $page->template_type;

                // Skip blank (custom HTML) pages
                if ($pageTpl === 'blank') {
                    $skippedCount++;
                    continue;
                }

                // If filtering by template, check if page matches
                if ($normalizedFilters) {
                    if (!in_array($pageTpl, $normalizedFilters, true)) {
                        $skippedCount++;
                        continue;
                    }
                }

                // Redeploy page with latest template from package
                $pending = dispatch(function () use ($page) {
                    app(\App\Services\DeploymentService::class)->deployPage($page);
                });
                if (method_exists($pending, 'afterResponse')) {
                    $pending->afterResponse();
                }
                $updatedCount++;
            }
            $domainParts = explode('.', $website->domain);
            $mainDomain = count($domainParts) > 2 ? implode('.', array_slice($domainParts, -2)) : $website->domain;
            $mainWebsite = Website::where('domain', $mainDomain)
                ->where('type', 'laravel1')
                ->where('status', 'deployed')
                ->first();

            $homeQueued = 0;
            $catQueued = 0;
            if ($mainWebsite) {
                $pendingHome = \App\Jobs\DeployLaravel1Homepage::dispatch($mainWebsite->id);
                if (method_exists($pendingHome, 'afterResponse')) { $pendingHome->afterResponse(); }
                $homeQueued = 1;
                $folders = \App\Models\Folder::where('website_id', $mainWebsite->id)->get();
                foreach ($folders as $folder) {
                    $pendingCat = \App\Jobs\DeployLaravel1CategoryPage::dispatch($folder->id);
                    if (method_exists($pendingCat, 'afterResponse')) { $pendingCat->afterResponse(); }
                }
                $catQueued = $folders->count();
            }

            $msg = "Updated {$updatedCount} pages";
            if ($homeQueued || $catQueued) {
                $msg .= "; Homepage and {$catQueued} categories queued";
            }
            return response()->json([
                'message' => $msg,
                'updated' => $updatedCount,
                'skipped' => $skippedCount,
                'homepage_queued' => $homeQueued,
                'categories_queued' => $catQueued
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Template update failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function createDefaultPages(Website $website): void
    {
        // Create homepage
        $website->pages()->create([
            'path' => '/',
            'filename' => 'index.html',
            'title' => 'Trang chủ',
            'content' => '<h1>Trang chủ</h1>\n<p>Chào mừng đến với website của bạn!</p>'
        ]);

        // Create about page
        $website->pages()->create([
            'path' => '/gioi-thieu',
            'filename' => 'about.html',
            'title' => 'Giới thiệu',
            'content' => '<h1>Giới thiệu</h1>\n<p>Trang giới thiệu của bạn.</p>'
        ]);
    }
}
