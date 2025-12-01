<?php

namespace App\Http\Controllers;

use App\Models\CloudflareAccount;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;

class CloudflareAccountController extends Controller
{
    /**
     * Get all Cloudflare accounts
     */
    public function index(): JsonResponse
    {
        $accounts = CloudflareAccount::orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json($accounts);
    }

    /**
     * Store a new Cloudflare account
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'api_key' => 'required|string',
            'is_default' => 'boolean',
        ]);

        // Encrypt API key
        $validated['api_key'] = Crypt::encryptString($validated['api_key']);

        $account = CloudflareAccount::create($validated);

        // Set as default if requested
        if (!empty($validated['is_default'])) {
            $account->setAsDefault();
        }

        return response()->json($account, 201);
    }

    /**
     * Show a specific Cloudflare account
     */
    public function show(CloudflareAccount $cloudflareAccount): JsonResponse
    {
        // Decrypt API key for display (masked)
        $account = $cloudflareAccount->toArray();
        $decrypted = Crypt::decryptString($cloudflareAccount->api_key);
        $account['api_key_masked'] = substr($decrypted, 0, 8) . str_repeat('*', strlen($decrypted) - 12) . substr($decrypted, -4);

        return response()->json($account);
    }

    /**
     * Update a Cloudflare account
     */
    public function update(Request $request, CloudflareAccount $cloudflareAccount): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'api_key' => 'sometimes|string',
            'is_default' => 'boolean',
        ]);

        // Encrypt API key if provided
        if (isset($validated['api_key'])) {
            $validated['api_key'] = Crypt::encryptString($validated['api_key']);
        }

        $cloudflareAccount->update($validated);

        // Set as default if requested
        if (!empty($validated['is_default'])) {
            $cloudflareAccount->setAsDefault();
        }

        return response()->json($cloudflareAccount);
    }

    /**
     * Delete a Cloudflare account
     */
    public function destroy(CloudflareAccount $cloudflareAccount): JsonResponse
    {
        // Check if any websites are using this account
        if ($cloudflareAccount->websites()->count() > 0) {
            return response()->json([
                'error' => 'Cannot delete Cloudflare account that is being used by websites'
            ], 422);
        }

        $cloudflareAccount->delete();

        return response()->json(null, 204);
    }

    /**
     * Set an account as default
     */
    public function setDefault(CloudflareAccount $cloudflareAccount): JsonResponse
    {
        $cloudflareAccount->setAsDefault();

        return response()->json($cloudflareAccount);
    }
}
