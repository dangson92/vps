<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Services\DeploymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebsiteAssetsController extends Controller
{
    private function ensureMainDomain(Website $website): bool
    {
        $parts = explode('.', $website->domain);
        return count($parts) <= 2;
    }

    private function storeUploaded(Request $request, Website $website, string $targetName): array
    {
        $request->validate(['file' => 'required|file|mimes:png,jpg,jpeg,webp,svg,ico|max:5120']);
        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return ['error' => 'Invalid file'];
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        $ext = preg_replace('/[^a-z0-9]/', '', $ext);
        $mainDomain = $website->domain;
        $uploadDir = public_path('uploads/' . $mainDomain . '/assets');
        if (!is_dir($uploadDir)) {
            try {
                @mkdir($uploadDir, 0775, true);
            } catch (\Throwable $e) {
                return ['error' => 'Failed to create upload directory'];
            }
        }
        if (!is_dir($uploadDir)) {
            return ['error' => 'Upload directory not available'];
        }
        $filename = $targetName . '.' . $ext;
        try {
            $file->move($uploadDir, $filename);
        } catch (\Throwable $e) {
            return ['error' => 'Failed to store uploaded file'];
        }

        $relative = '/uploads/' . $mainDomain . '/assets/' . $filename;
        $url = url($relative);
        return ['path' => $relative, 'url' => $url, 'filename' => $filename];
    }

    private function currentSettings(Website $website): array
    {
        $raw = $website->custom_settings;
        if (is_array($raw)) return $raw;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) return $decoded;
        }
        return [];
    }

    public function uploadLogoHeader(Request $request, Website $website, DeploymentService $deployment): JsonResponse
    {
        if (!$this->ensureMainDomain($website)) {
            return response()->json(['error' => 'Settings only for main domain'], 422);
        }

        $stored = $this->storeUploaded($request, $website, 'site-logo-header');
        if (isset($stored['error'])) return response()->json($stored, 422);

        $settings = $this->currentSettings($website);
        $settings['logo_header_path'] = $stored['path'];
        $settings['logo_header_url'] = $stored['url'];
        $website->custom_settings = $settings;
        $website->save();

        // Deploy assets to worker root (assets folder)
        try { $deployment->deployWebsiteAssets($website); } catch (\Throwable $e) {}

        return response()->json(['status' => 'ok', 'url' => $stored['url'], 'filename' => $stored['filename'], 'source_path' => $stored['path']]);
    }

    public function uploadLogoFooter(Request $request, Website $website, DeploymentService $deployment): JsonResponse
    {
        if (!$this->ensureMainDomain($website)) {
            return response()->json(['error' => 'Settings only for main domain'], 422);
        }

        $stored = $this->storeUploaded($request, $website, 'site-logo-footer');
        if (isset($stored['error'])) return response()->json($stored, 422);

        $settings = $this->currentSettings($website);
        $settings['logo_footer_path'] = $stored['path'];
        $settings['logo_footer_url'] = $stored['url'];
        $website->custom_settings = $settings;
        $website->save();

        try { $deployment->deployWebsiteAssets($website); } catch (\Throwable $e) {}

        return response()->json(['status' => 'ok', 'url' => $stored['url'], 'filename' => $stored['filename'], 'source_path' => $stored['path']]);
    }

    public function uploadFavicon(Request $request, Website $website, DeploymentService $deployment): JsonResponse
    {
        if (!$this->ensureMainDomain($website)) {
            return response()->json(['error' => 'Settings only for main domain'], 422);
        }

        $request->validate(['file' => 'required|file|mimes:ico,png,jpg,jpeg,svg|max:2048']);
        $file = $request->file('file');
        if (!$file || !$file->isValid()) return response()->json(['error' => 'Invalid file'], 422);

        $ext = strtolower($file->getClientOriginalExtension() ?: 'ico');
        $ext = preg_replace('/[^a-z0-9]/', '', $ext);
        $mainDomain = $website->domain;
        $uploadDir = public_path('uploads/' . $mainDomain . '/assets');
        if (!is_dir($uploadDir)) {
            try {
                @mkdir($uploadDir, 0775, true);
            } catch (\Throwable $e) {
                return response()->json(['error' => 'Failed to create upload directory'], 500);
            }
        }
        if (!is_dir($uploadDir)) return response()->json(['error' => 'Upload directory not available'], 500);
        $filename = 'favicon.' . $ext;
        try {
            $file->move($uploadDir, $filename);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to store uploaded file'], 500);
        }

        $relative = '/uploads/' . $mainDomain . '/assets/' . $filename;
        $url = url($relative);

        $settings = $this->currentSettings($website);
        $settings['favicon_path'] = $relative;
        $settings['favicon_url'] = $url;
        $website->custom_settings = $settings;
        $website->save();

        try { $deployment->deployWebsiteAssets($website); } catch (\Throwable $e) {}

        return response()->json(['status' => 'ok', 'url' => $url, 'filename' => $filename, 'source_path' => $relative]);
    }
}

