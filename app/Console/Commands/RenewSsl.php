<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Website;
use App\Services\SslService;
use Carbon\Carbon;

class RenewSsl extends Command
{
    protected $signature = 'ssl:renew';
    protected $description = 'Renew expiring SSL certificates';

    public function handle(SslService $sslService)
    {
        $expiringWebsites = Website::where('ssl_enabled', true)
            ->where('ssl_expires_at', '<=', Carbon::now()->addDays(30))
            ->get();
        
        foreach ($expiringWebsites as $website) {
            try {
                $sslService->renew($website);
                $this->info("Renewed SSL for {$website->domain}");
            } catch (\Exception $e) {
                $this->error("Failed to renew SSL for {$website->domain}: " . $e->getMessage());
            }
        }
        
        return Command::SUCCESS;
    }
}
