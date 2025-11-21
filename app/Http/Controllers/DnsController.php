<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Services\DnsService;
use App\Services\SslService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DnsController extends Controller
{
    private DnsService $dnsService;

    public function __construct(DnsService $dnsService)
    {
        $this->dnsService = $dnsService;
    }

    public function createRecord(Request $request, Website $website): JsonResponse
    {
        try {
            $record = $this->dnsService->createRecord($request, $website);
            return response()->json($record, 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create DNS record',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteRecord(string $recordId): JsonResponse
    {
        try {
            $this->dnsService->deleteRecord($recordId);
            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete DNS record',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}