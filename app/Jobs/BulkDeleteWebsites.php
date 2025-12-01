<?php

namespace App\Jobs;

use App\Models\Website;
use App\Services\DeploymentService;
use App\Services\DnsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class BulkDeleteWebsites implements ShouldQueue
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
    public function handle(DeploymentService $deploymentService): void
    {
        foreach ($this->websiteIds as $websiteId) {
            try {
                $website = Website::find($websiteId);

                if (!$website) {
                    Log::warning("Website {$websiteId} not found for bulk delete");
                    continue;
                }

                $domain = $website->domain;

                // Remove from VPS
                $deploymentService->removeWebsite($website);

                // Delete DNS records
                $dnsService = new DnsService($website);
                $dnsService->deleteWebsiteRecords($website);

                // Delete monitoring stats (FK constraint)
                try {
                    $website->monitoringStats()->delete();
                } catch (\Throwable $e) {
                }

                // Delete pages before deleting website to satisfy FK constraints
                $website->pages()->delete();

                $website->delete();

                Log::info("Successfully deleted website {$websiteId} ({$domain})");
            } catch (\Throwable $e) {
                Log::error("Failed to delete website {$websiteId}: " . $e->getMessage(), [
                    'exception' => $e
                ]);
            }
        }
    }
}
