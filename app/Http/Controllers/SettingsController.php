<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

class SettingsController extends Controller
{
    private array $allowedKeys = [
        'APP_NAME',
        'APP_URL',
        'SSL_EMAIL',
        'CLOUDFLARE_API_TOKEN',
        'CLOUDFLARE_ZONE_ID',
        'ADMIN_NAME',
        'ADMIN_EMAIL',
        'ADMIN_USERNAME',
        'ADMIN_PASSWORD',
    ];

    public function index(): JsonResponse
    {
        $settings = [];
        foreach ($this->allowedKeys as $key) {
            $settings[$key] = env($key);
        }
        return response()->json($settings);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->only($this->allowedKeys);
        $envPath = base_path('.env');
        $envDir = base_path();

        if (!file_exists($envPath)) {
            // Try to create empty .env
            if (!is_writable($envDir)) {
                return response()->json(['error' => 'Project directory is not writable'], 422);
            }
            file_put_contents($envPath, "");
        }

        if (!is_writable($envPath)) {
            return response()->json(['error' => '.env is not writable'], 422);
        }

        $env = file_get_contents($envPath);

        foreach ($data as $key => $value) {
            $pattern = "/^" . preg_quote($key, '/') . "=.*/m";
            $line = $key . '=' . $this->escapeEnvValue($value);
            if (preg_match($pattern, $env)) {
                $env = preg_replace($pattern, $line, $env);
            } else {
                $env .= "\n" . $line;
            }
        }

        $written = @file_put_contents($envPath, $env);
        if ($written === false) {
            return response()->json(['error' => 'Failed to write .env'], 500);
        }

        try {
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Config/cache clear failed: ' . $e->getMessage()], 500);
        }

        return response()->json(['status' => 'updated']);
    }

    private function escapeEnvValue($value): string
    {
        $str = (string) $value;
        if (str_contains($str, ' ') || str_contains($str, '#')) {
            return '"' . str_replace('"', '\\"', $str) . '"';
        }
        return $str;
    }
}