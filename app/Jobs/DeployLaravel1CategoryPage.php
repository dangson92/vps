<?php

namespace App\Jobs;

use App\Models\Folder;
use App\Services\DeploymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeployLaravel1CategoryPage implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $folderId
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(DeploymentService $deploymentService): void
    {
        $folder = Folder::find($this->folderId);

        if (!$folder || !$folder->website) {
            return;
        }

        $website = $folder->website;

        if ($website->type !== 'laravel1' || $website->status !== 'deployed') {
            return;
        }

        try {
            $deploymentService->deployLaravel1CategoryPage($folder);
        } catch (\Exception $e) {
            // Log error but don't fail the job
            \Log::error("Failed to deploy Laravel1 category page for folder {$this->folderId}: " . $e->getMessage());
        }
    }
}
