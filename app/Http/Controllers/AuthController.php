<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string'
        ]);

        $username = env('ADMIN_USERNAME');
        $password = env('ADMIN_PASSWORD');
        $appKey = env('APP_KEY', 'vps-manager');
        if (!$username || !$password) {
            return response()->json(['error' => 'Admin credentials not configured'], 500);
        }
        if (!hash_equals($username, $validated['username']) || !hash_equals($password, $validated['password'])) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $rawToken = Str::random(64);
        $hash = hash('sha256', $rawToken);

        DB::table('admin_tokens')->insert([
            'token_hash' => $hash,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->header('User-Agent'), 0, 255),
            'revoked' => false,
            'expires_at' => Carbon::now()->addDays(1),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return response()->json(['token' => $rawToken]);
    }
}