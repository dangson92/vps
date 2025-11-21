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
    private DnsService $dnsService;
    private SslService $sslService;
    private MonitoringService $monitoringService;

    public function __construct(
        DeploymentService $deploymentService,
        DnsService $dnsService,
        SslService $sslService,
        MonitoringService $monitoringService
    ) {
        $this->deploymentService = $deploymentService;
        $this->dnsService = $dnsService;
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
            'vps_server_id' => 'required|exists:vps_servers,id',
            'wordpress_template' => 'required_if:type,wordpress|string|max:255',
        ]);

        $vps = VpsServer::findOrFail($validated['vps_server_id']);

        if (!$vps->isActive()) {
            return response()->json(['error' => 'VPS server is not active'], 422);
        }

        $website = new Website([
            'domain' => $validated['domain'],
            'type' => $validated['type'],
            'vps_server_id' => $validated['vps_server_id'],
            'document_root' => "/var/www/{$validated['domain']}",
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
        $this->dnsService->deleteWebsiteRecords($website);
        
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
            $this->dnsService->createRecords($website);
            
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
