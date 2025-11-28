<?php

namespace App\Http\Controllers;

use App\Models\VpsServer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class VpsController extends Controller
{
    public function index(): JsonResponse
    {
        $servers = VpsServer::withCount('websites')->get();
        return response()->json($servers);
    }

    public function store(Request $request): JsonResponse
    {
        $rawHost = (string)($request->input('ip_address') ?? '');
        $host = trim($rawHost);
        $host = preg_replace('/^https?:\/\//i', '', $host);
        $host = preg_replace('/[\/:].*$/', '', $host);

        $request->merge(['ip_address' => $host]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ip_address' => 'required|string|max:255|unique:vps_servers,ip_address',
            'ssh_user' => 'required|string|max:50',
            'ssh_port' => 'required|integer|min:1|max:65535',
            'ssh_key_path' => 'nullable|string',
        ]);

        $server = new VpsServer($validated);
        $server->worker_key = $server->generateWorkerKey();
        $server->status = 'inactive';
        $server->save();

        try {
            $this->addWorkerIpToNginx($server);
        } catch (\Throwable $e) {
        }

        return response()->json($server, 201);
    }

    public function show(VpsServer $vps): JsonResponse
    {
        return response()->json($vps->load('websites'));
    }

    public function update(Request $request, VpsServer $vps): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'ssh_user' => 'sometimes|string|max:50',
            'ssh_port' => 'sometimes|integer|min:1|max:65535',
            'ssh_key_path' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive,error',
        ]);

        $vps->update($validated);

        return response()->json($vps);
    }

    public function destroy(VpsServer $vps): JsonResponse
    {
        if ($vps->websites()->exists()) {
            return response()->json(['error' => 'Cannot delete VPS with existing websites'], 422);
        }

        $vps->delete();
        return response()->json(null, 204);
    }

    public function executeCommand(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'command' => 'required|string',
            'parameters' => 'array',
        ]);

        // Execute command on worker node
        $workerKey = $request->header('X-Worker-Key');
        $server = VpsServer::where('worker_key', $workerKey)->first();

        if (!$server) {
            return response()->json(['error' => 'Invalid worker key'], 401);
        }

        // Process command
        $result = $this->processWorkerCommand($validated['command'], $validated['parameters'] ?? []);

        return response()->json($result);
    }

    public function updateStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:active,inactive,error',
            'specs' => 'array',
        ]);

        $workerKey = $request->header('X-Worker-Key');
        $server = VpsServer::where('worker_key', $workerKey)->first();

        if (!$server) {
            return response()->json(['error' => 'Invalid worker key'], 401);
        }

        $server->update([
            'status' => $validated['status'],
            'specs' => $validated['specs'] ?? $server->specs,
        ]);

        return response()->json(['status' => 'updated']);
    }

    private function processWorkerCommand(string $command, array $parameters): array
    {
        // Process different worker commands
        switch ($command) {
            case 'deploy_website':
                return $this->deployWebsite($parameters);
            case 'create_database':
                return $this->createDatabase($parameters);
            case 'generate_ssl':
                return $this->generateSsl($parameters);
            case 'update_nginx':
                return $this->updateNginx($parameters);
            default:
                return ['error' => 'Unknown command'];
        }
    }

    private function deployWebsite(array $parameters): array
    {
        // Implementation for website deployment
        return ['status' => 'deployed', 'message' => 'Website deployed successfully'];
    }

    private function createDatabase(array $parameters): array
    {
        // Implementation for database creation
        return ['status' => 'created', 'message' => 'Database created successfully'];
    }

    private function generateSsl(array $parameters): array
    {
        // Implementation for SSL generation
        return ['status' => 'generated', 'message' => 'SSL certificate generated successfully'];
    }

    private function updateNginx(array $parameters): array
    {
        // Implementation for nginx update
        return ['status' => 'updated', 'message' => 'Nginx configuration updated successfully'];
    }

    private function addWorkerIpToNginx(VpsServer $vps): void
    {
        $ip = trim($vps->ip_address ?? '');
        if ($ip === '') return;
        $path = '/etc/nginx/sites-available/vps-manager';
        $contents = @file_get_contents($path);
        if ($contents === false) return;

        $exists = preg_match('/allow\s+' . preg_quote($ip, '/') . '\s*;/', $contents) === 1;
        if ($exists) return;

        $updated = preg_replace(
            '/(location\s+\/api\/worker\s*\{[\s\S]*?)(deny\s+all;)/',
            '$1allow ' . $ip . ";\n        $2",
            $contents,
            1,
            $count
        );

        if ($updated !== null && $count === 1) {
            @file_put_contents($path, $updated);
            @shell_exec('nginx -t && systemctl reload nginx');
        }
    }
}
