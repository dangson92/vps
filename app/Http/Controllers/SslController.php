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
            return response()->json([
                'message' => 'SSL certificate generated successfully',
                'website' => $website->fresh()
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'SSL generation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}