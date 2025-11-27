<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Services\SslService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SslController extends Controller
{
    private SslService $sslService;

    public function __construct(SslService $sslService)
    {
        $this->sslService = $sslService;
    }

    public function generate(Request $request, Website $website): JsonResponse
    {
        try {
            $this->sslService->generate($website);
            $fresh = $website->fresh();

            if ($fresh->type === 'laravel1') {
                $parts = explode('.', $fresh->domain);
                $isSub = count($parts) > 2;
                if ($isSub) {
                    $pages = $fresh->pages;
                    foreach ($pages as $page) {
                        $pending = dispatch(function () use ($page) {
                            app(\App\Services\DeploymentService::class)->deployPage($page);
                        });
                        if (method_exists($pending, 'afterResponse')) { $pending->afterResponse(); }
                    }
                } else {
                    $pendingHome = \App\Jobs\DeployLaravel1Homepage::dispatch($fresh->id);
                    if (method_exists($pendingHome, 'afterResponse')) { $pendingHome->afterResponse(); }
                    $folders = \App\Models\Folder::where('website_id', $fresh->id)->get();
                    foreach ($folders as $folder) {
                        $pendingCat = \App\Jobs\DeployLaravel1CategoryPage::dispatch($folder->id);
                        if (method_exists($pendingCat, 'afterResponse')) { $pendingCat->afterResponse(); }
                    }
                }
            }

            return response()->json([
                'message' => 'SSL certificate generated successfully',
                'website' => $fresh
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'SSL generation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
