<?php

namespace App\Jobs;

use App\Models\Website;
use App\Services\DeploymentService;
use App\Services\DnsService;
use App\Services\SslService;
use App\Services\MonitoringService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class BulkDeployWebsites implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $websiteIds
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(
        DeploymentService $deploymentService,
        SslService $sslService,
        MonitoringService $monitoringService
    ): void {
        foreach ($this->websiteIds as $websiteId) {
            try {
                $website = Website::find($websiteId);

                if (!$website) {
                    Log::warning("Website {$websiteId} not found for bulk deploy");
                    continue;
                }

                if ($website->status === 'deploying') {
                    Log::info("Website {$websiteId} is already being deployed, skipping");
                    continue;
                }

                $website->update(['status' => 'deploying']);

                $deploymentService->deploy($website);
                $deploymentService->publishAllPages($website);

                // Create DNS records
                $dnsService = new DnsService($website);
                $dnsService->createRecords($website);

                // Generate SSL if enabled
                if ($website->ssl_enabled) {
                    $sslService->generate($website);
                }

                $website->update([
                    'status' => 'deployed',
                    'deployed_at' => now(),
                    'deployed_version' => $website->content_version,
                ]);
                $monitoringService->checkUptime($website);

                Log::info("Successfully deployed website {$websiteId} ({$website->domain})");
            } catch (\Throwable $e) {
                Log::error("Failed to deploy website {$websiteId}: " . $e->getMessage(), [
                    'exception' => $e
                ]);

                if (isset($website)) {
                    $website->update(['status' => 'error']);
                }
            }
        }
    }
}
