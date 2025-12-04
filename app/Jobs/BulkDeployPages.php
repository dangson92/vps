<?php

namespace App\Jobs;

use App\Models\Page;
use App\Models\Website;
use App\Services\DeploymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class BulkDeployPages implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public array $pageIds,
        public int $websiteId
    ) {}

    public function handle(DeploymentService $deploymentService): void
    {
        $website = Website::find($this->websiteId);
        if (!$website) {
            Log::warning('BulkDeployPages: Website not found', ['website_id' => $this->websiteId]);
            return;
        }

        Log::info('BulkDeployPages: Starting', [
            'website_id' => $this->websiteId,
            'page_count' => count($this->pageIds)
        ]);

        // 1. Deploy selected pages
        foreach ($this->pageIds as $pageId) {
            try {
                $page = Page::find($pageId);
                if ($page && $page->website_id === $this->websiteId) {
                    $deploymentService->deployPage($page);
                    Log::info('BulkDeployPages: Deployed page', [
                        'page_id' => $pageId,
                        'title' => $page->title
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('BulkDeployPages: Failed to deploy page', [
                    'page_id' => $pageId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // 2. Deploy homepage
        try {
            $deploymentService->deployLaravel1Homepage($website);
            Log::info('BulkDeployPages: Deployed homepage', ['website_id' => $this->websiteId]);
        } catch (\Exception $e) {
            Log::error('BulkDeployPages: Failed to deploy homepage', [
                'website_id' => $this->websiteId,
                'error' => $e->getMessage()
            ]);
        }

        // 3. Deploy listing pages (categories)
        try {
            $deploymentService->deployLaravel1AllCategories($website);
            Log::info('BulkDeployPages: Deployed all categories', ['website_id' => $this->websiteId]);
        } catch (\Exception $e) {
            Log::error('BulkDeployPages: Failed to deploy categories', [
                'website_id' => $this->websiteId,
                'error' => $e->getMessage()
            ]);
        }

        Log::info('BulkDeployPages: Completed', [
            'website_id' => $this->websiteId,
            'page_count' => count($this->pageIds)
        ]);
    }
}
