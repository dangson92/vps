<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Services\DnsService;
use App\Services\SslService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DnsController extends Controller
{
    public function createRecord(Request $request, Website $website): JsonResponse
    {
        try {
            $dnsService = new DnsService($website);
            $record = $dnsService->createRecord($request, $website);
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
            // For delete, we need to find the website first
            $record = \App\Models\DnsRecord::findOrFail($recordId);
            $dnsService = new DnsService($record->website);
            $dnsService->deleteRecord($recordId);
            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete DNS record',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}