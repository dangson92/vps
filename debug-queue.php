#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Queue Debug ===\n\n";

// 1. Check queue configuration
echo "1. Queue Configuration:\n";
echo "   Connection: " . config('queue.default') . "\n";
echo "   Driver: " . config('queue.connections.database.driver') . "\n\n";

// 2. Check jobs table
echo "2. Jobs Table:\n";
try {
    $pendingJobs = DB::table('jobs')->count();
    echo "   Pending jobs: {$pendingJobs}\n";

    if ($pendingJobs > 0) {
        echo "   Jobs list:\n";
        DB::table('jobs')->select('id', 'queue', 'attempts', 'created_at')->get()->each(function($job) {
            $payload = json_decode($job->payload);
            echo "   - Job #{$job->id}: {$payload->displayName} (attempts: {$job->attempts})\n";
        });
    }
} catch (\Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

// 3. Check failed jobs
echo "3. Failed Jobs:\n";
try {
    $failedJobs = DB::table('failed_jobs')->count();
    echo "   Failed jobs: {$failedJobs}\n";

    if ($failedJobs > 0) {
        echo "   Recent failures:\n";
        DB::table('failed_jobs')->orderBy('failed_at', 'desc')->limit(5)->get()->each(function($job) {
            $payload = json_decode($job->payload);
            echo "   - {$payload->displayName}\n";
            echo "     Failed at: {$job->failed_at}\n";
            echo "     Exception: " . substr($job->exception, 0, 200) . "...\n\n";
        });
    }
} catch (\Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

// 4. Test dispatching a job
echo "4. Test Dispatch:\n";
try {
    $website = App\Models\Website::where('type', 'laravel1')
        ->where('status', 'deployed')
        ->whereRaw('LENGTH(domain) - LENGTH(REPLACE(domain, ".", "")) = 1') // Only main domains
        ->first();

    if ($website) {
        echo "   Testing with website: {$website->domain}\n";

        // Dispatch test job
        \App\Jobs\DeployLaravel1Homepage::dispatch($website->id);

        echo "   ✓ Job dispatched successfully\n";

        // Check if it was added to queue
        sleep(1);
        $newCount = DB::table('jobs')->count();
        echo "   Jobs in queue now: {$newCount}\n";

        if ($newCount > 0) {
            echo "   ✓ Job added to queue!\n";
        } else {
            echo "   ✗ Job NOT in queue (might have been processed immediately or failed)\n";
        }
    } else {
        echo "   No suitable website found for testing\n";
    }
} catch (\Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
}
echo "\n";

// 5. Check queue worker process
echo "5. Queue Worker Status:\n";
echo "   Run on server: sudo systemctl status vps-queue-worker\n";
echo "   View logs: sudo journalctl -u vps-queue-worker -n 50\n\n";

echo "=== Debug Complete ===\n";
