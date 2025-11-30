<?php

namespace App\Jobs;

use App\Models\Website;
use App\Services\DeploymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RedeployAllPages implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $websiteId
    ) {}

    public function handle(DeploymentService $deploymentService): void
    {
        $website = Website::find($this->websiteId);
        if (!$website) {
            Log::warning('RedeployAllPages: Website not found', ['website_id' => $this->websiteId]);
            return;
        }

        Log::info('RedeployAllPages: Starting', ['website_id' => $this->websiteId]);

        try {
            $deploymentService->deployLaravel1AllPages($website);
            Log::info('RedeployAllPages: Completed successfully', ['website_id' => $this->websiteId]);
        } catch (\Exception $e) {
            Log::error('RedeployAllPages: Failed', [
                'website_id' => $this->websiteId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
