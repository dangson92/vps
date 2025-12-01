<?php

namespace App\Jobs;

use App\Models\Page;
use App\Services\DeploymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeployLaravel1Page implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $pageId,
        public ?string $oldPath = null,
        public ?string $oldFilename = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(DeploymentService $deploymentService): void
    {
        $page = Page::find($this->pageId);

        if (!$page) {
            Log::warning("Page {$this->pageId} not found for deployment");
            return;
        }

        Log::info("Deploying page: {$page->path} for website: {$page->website->domain}");

        $deploymentService->deployPage($page, $this->oldPath, $this->oldFilename);

        Log::info("Successfully deployed page: {$page->path}");
    }
}
