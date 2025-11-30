<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Services\DeploymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebsiteSettingsController extends Controller
{
    private DeploymentService $deploymentService;

    public function __construct(DeploymentService $deploymentService)
    {
        $this->deploymentService = $deploymentService;
    }
    public function show(Website $website): JsonResponse
    {
        $parts = explode('.', $website->domain);
        if (count($parts) > 2) {
            $rootDomain = $parts[count($parts)-2] . '.' . $parts[count($parts)-1];
            $root = \App\Models\Website::where('domain', $rootDomain)->first();
            if ($root) $website = $root;
        }

        return response()->json($website->custom_settings ?? []);
    }

    public function update(Request $request, Website $website): JsonResponse
    {
        $parts = explode('.', $website->domain);
        if (count($parts) > 2) {
            $rootDomain = $parts[count($parts)-2] . '.' . $parts[count($parts)-1];
            $root = \App\Models\Website::where('domain', $rootDomain)->first();
            if ($root) $website = $root;
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'logo_header_url' => 'nullable|string|max:2048',
            'logo_footer_url' => 'nullable|string|max:2048',
            'favicon_url' => 'nullable|string|max:2048',
            'custom_head_html' => 'nullable|string',
            'custom_body_html' => 'nullable|string',
            'custom_footer_html' => 'nullable|string',
            'menu_html' => 'nullable|string',
            'menu' => 'nullable|array',
            'footer_links_html' => 'nullable|string',
            'footer_columns' => 'nullable|array',
        ]);

        $existing = is_array($website->custom_settings) ? $website->custom_settings : (is_string($website->custom_settings) ? (json_decode($website->custom_settings, true) ?: []) : []);
        $settings = array_merge($existing, $validated);
        $website->custom_settings = $settings;
        $website->save();

        // Redeploy pages to apply new settings (only for laravel1 sites)
        if ($website->type === 'laravel1' && $website->isDeployed()) {
            try {
                // Deploy assets (logo/favicon) to VPS first
                $this->deploymentService->deployWebsiteAssets($website);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to deploy assets', [
                    'website_id' => $website->id,
                    'error' => $e->getMessage()
                ]);
            }

            try {
                // Redeploy homepage with new settings
                $this->deploymentService->deployLaravel1Homepage($website);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to deploy homepage', [
                    'website_id' => $website->id,
                    'error' => $e->getMessage()
                ]);
            }

            try {
                // Redeploy all category pages with new settings
                $this->deploymentService->deployLaravel1AllCategories($website);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to deploy categories', [
                    'website_id' => $website->id,
                    'error' => $e->getMessage()
                ]);
            }

            try {
                // Redeploy all page details with new settings
                \Illuminate\Support\Facades\Log::info('Starting to deploy all pages', [
                    'website_id' => $website->id
                ]);
                $this->deploymentService->deployLaravel1AllPages($website);
                \Illuminate\Support\Facades\Log::info('Completed deploying all pages', [
                    'website_id' => $website->id
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to deploy pages', [
                    'website_id' => $website->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        return response()->json(['status' => 'updated', 'settings' => $website->custom_settings]);
    }
}
