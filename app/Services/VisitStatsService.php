<?php

namespace App\Services;

use App\Models\Client;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\Cache;

class VisitStatsService
{
    private const CACHE_TTL_SECONDS = 300;

    /**
     * Visit counts for the dashboard, cached individually to reduce database load.
     */
    public function summary(): array
    {
        return [
            'total_visits' => $this->cachedCount('visit_count', fn () => Client::count()),
            'today_count' => $this->cachedCount('today_visits', fn () => Client::whereDate('created_at', Carbon::today())->count()),
            'weekly_count' => $this->cachedCount('weekly_visits', fn () => Client::whereBetween('created_at', [
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek(),
            ])->count()),
            'monthly_count' => $this->cachedCount('monthly_visits', fn () => Client::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count()),
            'yearly_count' => $this->cachedCount('yearly_visits', fn () => Client::whereYear('created_at', now()->year)->count()),
        ];
    }

    private function cachedCount(string $key, Closure $query): int
    {
        return Cache::remember($key, self::CACHE_TTL_SECONDS, $query);
    }
}
