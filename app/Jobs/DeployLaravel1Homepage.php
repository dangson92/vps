<?php

namespace App\Jobs;

use App\Models\Website;
use App\Services\DeploymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeployLaravel1Homepage implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $websiteId
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(DeploymentService $deploymentService): void
    {
        $website = Website::find($this->websiteId);

        if (!$website || $website->type !== 'laravel1' || $website->status !== 'deployed') {
            return;
        }

        try {
            $deploymentService->deployLaravel1Homepage($website);
        } catch (\Exception $e) {
            // Log error but don't fail the job
            \Log::error("Failed to deploy Laravel1 homepage for website {$this->websiteId}: " . $e->getMessage());
        }
    }
}
