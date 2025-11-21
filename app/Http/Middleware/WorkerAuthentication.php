<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\VpsServer;

class WorkerAuthentication
{
    public function handle(Request $request, Closure $next)
    {
        $workerKey = $request->header('X-Worker-Key');
        
        if (!$workerKey) {
            return response()->json(['error' => 'Worker key required'], 401);
        }
        
        $server = VpsServer::where('worker_key', $workerKey)->first();
        
        if (!$server) {
            return response()->json(['error' => 'Invalid worker key'], 401);
        }
        
        // Add server to request for use in controllers
        $request->merge(['worker_server' => $server]);
        
        return $next($request);
    }
}