<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Models\MonitoringStat;
use App\Services\MonitoringService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class MonitoringController extends Controller
{
    private MonitoringService $monitoringService;

    public function __construct(MonitoringService $monitoringService)
    {
        $this->monitoringService = $monitoringService;
    }

    public function uptime(): JsonResponse
    {
        $stats = [
            'total_websites' => Website::count(),
            'online_websites' => Website::whereHas('monitoringStats', function ($query) {
                $query->where('date', today())->where('is_online', true);
            })->count(),
            'offline_websites' => Website::whereHas('monitoringStats', function ($query) {
                $query->where('date', today())->where('is_online', false);
            })->count(),
            'recent_checks' => MonitoringStat::where('date', today())
                ->orderBy('updated_at', 'desc')
                ->take(10)
                ->get()
        ];

        return response()->json($stats);
    }

    public function websiteStats(Website $website): JsonResponse
    {
        $period = request('period', '7days');
        $startDate = $this->getStartDate($period);

        $stats = MonitoringStat::where('website_id', $website->id)
            ->where('date', '>=', $startDate)
            ->orderBy('date', 'asc')
            ->get();

        $latest = $stats->sortByDesc('date')->first();
        $summary = [
            'total_visits' => $stats->sum('visits'),
            'total_unique_visitors' => $stats->sum('unique_visitors'),
            'total_bandwidth' => $this->formatBandwidth($stats->sum('bandwidth')),
            'average_response_time' => round($stats->avg('response_time'), 2),
            'uptime_percentage' => round($stats->where('is_online', true)->count() / $stats->count() * 100, 2),
            'current_is_online' => (bool) optional($latest)->is_online,
            'daily_stats' => $stats->map(function ($stat) {
                return [
                    'date' => $stat->date->format('Y-m-d'),
                    'visits' => $stat->visits,
                    'unique_visitors' => $stat->unique_visitors,
                    'bandwidth' => $this->formatBandwidth($stat->bandwidth),
                    'response_time' => $stat->response_time,
                    'is_online' => $stat->is_online,
                ];
            })
        ];

        return response()->json([
            'website' => $website->only(['id', 'domain', 'type', 'ssl_enabled']),
            'summary' => $summary,
            'raw_stats' => $stats
        ]);
    }

    private function getStartDate(string $period): Carbon
    {
        return match ($period) {
            '24hours' => now()->subDay(),
            '7days' => now()->subDays(7),
            '30days' => now()->subDays(30),
            '90days' => now()->subDays(90),
            default => now()->subDays(7),
        };
    }

    private function formatBandwidth(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
}