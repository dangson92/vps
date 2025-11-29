<?php

namespace App\Jobs;

use App\Models\Website;
use App\Services\DeploymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RedeployTemplateAssets implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $websiteId,
        public bool $refreshPages = true
    ) {}

    public function handle(DeploymentService $deploymentService): void
    {
        $website = Website::find($this->websiteId);
        if (!$website) return;

        $domainParts = explode('.', $website->domain);
        $mainDomain = count($domainParts) > 2 ? implode('.', array_slice($domainParts, -2)) : $website->domain;
        $mainWebsite = Website::where('domain', $mainDomain)
            ->where('type', 'laravel1')
            ->where('status', 'deployed')
            ->first();

        $domainPattern = '%.' . $mainDomain;
        $websites = Website::where(function($q) use ($mainDomain, $domainPattern) {
                $q->where('domain', $mainDomain)
                  ->orWhere('domain', 'like', $domainPattern);
            })
            ->where('type', 'laravel1')
            ->where('status', 'deployed')
            ->get();

        if ($mainWebsite) {
            try {
                // Deploy all template package assets ONLY to main domain
                $deploymentService->deployTemplatePackageAssets($mainWebsite);
            } catch (\Exception $e) {
            }
        }

        // If requested, redeploy pages to pick up render-time rewriting
        if ($this->refreshPages) {
            foreach ($websites as $site) {
                foreach ($site->pages as $page) {
                    try {
                        $deploymentService->deployPage($page);
                    } catch (\Exception $e) {}
                }
            }
        }
    }
}
