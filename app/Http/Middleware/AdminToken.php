<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class AdminToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->header('X-Admin-Token')
            ?? $request->cookie('X-Admin-Token')
            ?? $request->cookie('adminToken')
            ?? $request->query('token');

        if (!$token && $auth = $request->header('Authorization')) {
            if (str_starts_with($auth, 'Bearer ')) {
                $token = substr($auth, 7);
            }
        }
        $token = is_string($token) ? trim($token) : $token;
        $username = env('ADMIN_USERNAME');
        $password = env('ADMIN_PASSWORD');
        $appKey = env('APP_KEY', 'vps-manager');
        if (!$username || !$password) {
            return response()->json(['error' => 'Admin credentials not configured'], 401);
        }
        if ($token) {
            $hash = hash('sha256', $token);
            $now = Carbon::now();
            $row = DB::table('admin_tokens')
                ->where('token_hash', $hash)
                ->where('revoked', false)
                ->where(function ($q) use ($now) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
                })
                ->first();
            if ($row) {
                return $next($request);
            }
        }

        $message = $username . ':' . $password;
        $expected = hash_hmac('sha256', $message, $appKey);
        $expectedAlt = null;
        if (is_string($appKey) && str_starts_with($appKey, 'base64:')) {
            $raw = base64_decode(substr($appKey, 7));
            if ($raw !== false) {
                $expectedAlt = hash_hmac('sha256', $message, $raw);
            }
        }
        $validLegacy = $token && (hash_equals($expected, $token) || ($expectedAlt && hash_equals($expectedAlt, $token)));
        if ($validLegacy) {
            return $next($request);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }
}