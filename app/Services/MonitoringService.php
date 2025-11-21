<?php

namespace App\Services;

use App\Models\Website;
use App\Models\MonitoringStat;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class MonitoringService
{
    private $nginxLogPath = '/var/log/nginx/';

    public function checkUptime(Website $website): bool
    {
        $domains = ["https://{$website->domain}", "http://{$website->domain}"];
        $headers = [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ];
        foreach ($domains as $url) {
            try {
                $response = Http::timeout(10)
                    ->withOptions(['verify' => false, 'allow_redirects' => true])
                    ->withHeaders($headers)
                    ->get($url);

                $status = $response->status();
                $isOnline = $status >= 200 && $status < 500;
                $responseTime = null;
                try {
                    $stats = method_exists($response, 'transferStats') ? $response->transferStats() : null;
                    if ($stats && method_exists($stats, 'getHandlerStat')) {
                        $responseTime = $stats->getHandlerStat('total_time');
                    }
                } catch (\Throwable $e) {
                }

                $this->recordUptimeCheck($website, $isOnline, $responseTime);

                if ($isOnline) {
                    return true;
                }
            } catch (\Exception $e) {
            }
        }
        $this->recordUptimeCheck($website, false);
        return false;
    }

    public function parseNginxLogs(Website $website, string $logDate = null): array
    {
        $logDate = $logDate ?: now()->format('Y-m-d');
        $domain = $website->domain;
        $logFile = "{$this->nginxLogPath}{$domain}.access.log";

        if (!file_exists($logFile)) {
            Log::warning("Nginx log file not found", ['file' => $logFile]);
            return [];
        }

        $stats = [
            'visits' => 0,
            'unique_visitors' => 0,
            'bandwidth' => 0,
            'status_codes' => [],
            'user_agents' => [],
            'referrers' => [],
        ];

        $uniqueIps = [];
        $handle = fopen($logFile, 'r');

        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $parsed = $this->parseLogLine($line);
                
                if ($parsed && $parsed['date'] === $logDate) {
                    $stats['visits']++;
                    
                    if (!in_array($parsed['ip'], $uniqueIps)) {
                        $uniqueIps[] = $parsed['ip'];
                    }

                    $stats['bandwidth'] += $parsed['bytes_sent'];
                    
                    $statusCode = $parsed['status_code'];
                    $stats['status_codes'][$statusCode] = ($stats['status_codes'][$statusCode] ?? 0) + 1;
                    
                    if ($parsed['user_agent']) {
                        $ua = $this->simplifyUserAgent($parsed['user_agent']);
                        $stats['user_agents'][$ua] = ($stats['user_agents'][$ua] ?? 0) + 1;
                    }
                    
                    if ($parsed['referrer']) {
                        $ref = $this->simplifyReferrer($parsed['referrer']);
                        $stats['referrers'][$ref] = ($stats['referrers'][$ref] ?? 0) + 1;
                    }
                }
            }
            fclose($handle);
        }

        $stats['unique_visitors'] = count($uniqueIps);

        return $stats;
    }

    public function recordStats(Website $website, string $date = null): void
    {
        $date = $date ?: now()->format('Y-m-d');
        $logStats = $this->parseNginxLogs($website, $date);
        
        $stat = MonitoringStat::firstOrCreate(
            [
                'website_id' => $website->id,
                'date' => $date,
            ],
            [
                'visits' => $logStats['visits'] ?? 0,
                'unique_visitors' => $logStats['unique_visitors'] ?? 0,
                'bandwidth' => $logStats['bandwidth'] ?? 0,
                'is_online' => $this->checkUptime($website),
            ]
        );

        if (!$stat->wasRecentlyCreated) {
            $stat->update([
                'visits' => $logStats['visits'] ?? 0,
                'unique_visitors' => $logStats['unique_visitors'] ?? 0,
                'bandwidth' => $logStats['bandwidth'] ?? 0,
                'is_online' => $this->checkUptime($website),
            ]);
        }
    }

    private function recordUptimeCheck(Website $website, bool $isOnline, ?float $responseTime = null): void
    {
        $stat = MonitoringStat::firstOrCreate(
            [
                'website_id' => $website->id,
                'date' => now()->format('Y-m-d'),
            ],
            [
                'visits' => 0,
                'unique_visitors' => 0,
                'bandwidth' => 0,
                'is_online' => $isOnline,
                'response_time' => $responseTime ? (int)($responseTime * 1000) : null,
            ]
        );

        if (!$stat->wasRecentlyCreated) {
            $stat->update([
                'is_online' => $isOnline,
                'response_time' => $responseTime ? (int)($responseTime * 1000) : $stat->response_time,
            ]);
        }
    }

    private function parseLogLine(string $line): ?array
    {
        // Common Log Format: IP - - [date] "method path protocol" status bytes "referrer" "user_agent"
        $pattern = '/^(\S+) \S+ \S+ \[(.+?)\] "(\S+) (.*?) (\S+)" (\d+) (\d+) "(.*?)" "(.*?)"/';
        
        if (preg_match($pattern, $line, $matches)) {
            $date = Carbon::createFromFormat('d/M/Y:H:i:s O', $matches[2]);
            
            return [
                'ip' => $matches[1],
                'date' => $date->format('Y-m-d'),
                'method' => $matches[3],
                'path' => $matches[4],
                'protocol' => $matches[5],
                'status_code' => (int)$matches[6],
                'bytes_sent' => (int)$matches[7],
                'referrer' => $matches[8] !== '-' ? $matches[8] : null,
                'user_agent' => $matches[9] !== '-' ? $matches[9] : null,
            ];
        }

        return null;
    }

    private function simplifyUserAgent(string $userAgent): string
    {
        if (stripos($userAgent, 'bot') !== false) {
            return 'Bot';
        }
        if (stripos($userAgent, 'mobile') !== false) {
            return 'Mobile';
        }
        if (stripos($userAgent, 'chrome') !== false) {
            return 'Chrome';
        }
        if (stripos($userAgent, 'firefox') !== false) {
            return 'Firefox';
        }
        if (stripos($userAgent, 'safari') !== false) {
            return 'Safari';
        }
        
        return 'Other';
    }

    private function simplifyReferrer(string $referrer): string
    {
        $parsed = parse_url($referrer);
        return $parsed['host'] ?? 'Direct';
    }
}