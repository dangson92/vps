<?php

namespace App\Jobs;

use App\Models\Website;
use App\Services\SslService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class BulkInstallSsl implements ShouldQueue
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
    public function handle(SslService $sslService): void
    {
        foreach ($this->websiteIds as $websiteId) {
            try {
                $website = Website::find($websiteId);

                if (!$website) {
                    Log::warning("Website {$websiteId} not found for bulk SSL installation");
                    continue;
                }

                if ($website->status !== 'deployed') {
                    Log::info("Website {$websiteId} is not deployed, skipping SSL installation");
                    continue;
                }

                $sslService->generate($website);
                $website->update(['ssl_enabled' => true]);

                Log::info("Successfully installed SSL for website {$websiteId} ({$website->domain})");
            } catch (\Throwable $e) {
                Log::error("Failed to install SSL for website {$websiteId}: " . $e->getMessage(), [
                    'exception' => $e
                ]);
            }
        }
    }
}
