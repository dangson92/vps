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

                // 1. Deploy infrastructure
                $deploymentService->deploy($website);

                // 2. Deploy all pages synchronously (not queued)
                foreach ($website->pages as $page) {
                    try {
                        $deploymentService->deployPage($page);
                        Log::info("BulkDeployWebsites: Deployed page", [
                            'website_id' => $websiteId,
                            'page_id' => $page->id,
                            'path' => $page->path
                        ]);
                    } catch (\Exception $e) {
                        Log::error("BulkDeployWebsites: Failed to deploy page", [
                            'website_id' => $websiteId,
                            'page_id' => $page->id,
                            'path' => $page->path,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                // 3. Deploy homepage and category pages (ONLY for root domain laravel1 sites, NOT subdomains)
                if ($website->type === 'laravel1') {
                    // Check if this is a subdomain
                    $domainParts = explode('.', $website->domain);
                    $isSubdomain = count($domainParts) > 2;

                    if (!$isSubdomain) {
                        // This is a root domain, deploy homepage and categories
                        try {
                            $deploymentService->deployLaravel1Homepage($website);
                            Log::info("BulkDeployWebsites: Deployed homepage", ['website_id' => $websiteId]);
                        } catch (\Exception $e) {
                            Log::error("BulkDeployWebsites: Failed to deploy homepage", [
                                'website_id' => $websiteId,
                                'error' => $e->getMessage()
                            ]);
                        }

                        // 4. Deploy category pages
                        try {
                            $deploymentService->deployLaravel1AllCategories($website);
                            Log::info("BulkDeployWebsites: Deployed all categories", ['website_id' => $websiteId]);
                        } catch (\Exception $e) {
                            Log::error("BulkDeployWebsites: Failed to deploy categories", [
                                'website_id' => $websiteId,
                                'error' => $e->getMessage()
                            ]);
                        }
                    } else {
                        Log::info("BulkDeployWebsites: Skipping homepage/categories for subdomain", [
                            'website_id' => $websiteId,
                            'domain' => $website->domain
                        ]);
                    }
                }

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
