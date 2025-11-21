<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Website;
use App\Services\MonitoringService;

class ParseLogs extends Command
{
    protected $signature = 'logs:parse';
    protected $description = 'Parse nginx logs for statistics';

    public function handle(MonitoringService $monitoringService)
    {
        $websites = Website::where('status', 'deployed')->get();
        
        foreach ($websites as $website) {
            try {
                $monitoringService->recordStats($website);
                $this->info("Parsed logs for {$website->domain}");
            } catch (\Exception $e) {
                $this->error("Failed to parse logs for {$website->domain}: " . $e->getMessage());
            }
        }
        
        return Command::SUCCESS;
    }
}
