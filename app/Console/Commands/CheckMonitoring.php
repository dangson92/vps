<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MonitoringService;
use App\Models\Website;

class CheckMonitoring extends Command
{
    protected $signature = 'monitoring:check';
    protected $description = 'Check website uptime and record statistics';

    public function handle(MonitoringService $monitoringService)
    {
        $websites = Website::where('status', 'deployed')->get();
        
        foreach ($websites as $website) {
            try {
                $monitoringService->recordStats($website);
                $this->info("Checked {$website->domain}");
            } catch (\Exception $e) {
                $this->error("Failed to check {$website->domain}: " . $e->getMessage());
            }
        }
        
        return Command::SUCCESS;
    }
}
