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

        // 2. Deploy homepage and categories (ONLY for root domain, NOT subdomains)
        // Check if this is a subdomain
        $domainParts = explode('.', $website->domain);
        $isSubdomain = count($domainParts) > 2;

        if (!$isSubdomain) {
            // This is a root domain, deploy homepage and categories
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
        } else {
            Log::info('BulkDeployPages: Skipping homepage/categories for subdomain', [
                'website_id' => $this->websiteId,
                'domain' => $website->domain
            ]);
        }

        Log::info('BulkDeployPages: Completed', [
            'website_id' => $this->websiteId,
            'page_count' => count($this->pageIds)
        ]);
    }
}
