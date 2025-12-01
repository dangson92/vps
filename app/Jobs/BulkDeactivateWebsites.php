<?php

namespace App\Jobs;

use App\Models\Website;
use App\Services\DeploymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class BulkDeactivateWebsites implements ShouldQueue
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
                    Log::warning("Website {$websiteId} not found for bulk deactivate");
                    continue;
                }

                try {
                    $deploymentService->deactivateWebsite($website);
                } catch (\Exception $e) {
                }

                $website->update([
                    'status' => 'suspended',
                    'suspended_at' => now(),
                ]);

                Log::info("Successfully deactivated website {$websiteId} ({$website->domain})");
            } catch (\Throwable $e) {
                Log::error("Failed to deactivate website {$websiteId}: " . $e->getMessage(), [
                    'exception' => $e
                ]);
            }
        }
    }
}
