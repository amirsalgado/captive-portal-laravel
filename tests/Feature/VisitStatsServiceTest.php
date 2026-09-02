<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Services\VisitStatsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_counts_clients_by_period(): void
    {
        Carbon::setTestNow('2026-09-02 12:00:00');

        Client::factory()->create(['created_at' => '2026-09-02 08:00:00']);
        Client::factory()->create(['created_at' => '2026-08-15 08:00:00']);
        Client::factory()->create(['created_at' => '2025-01-01 08:00:00']);

        $stats = (new VisitStatsService)->summary();

        $this->assertSame(3, $stats['total_visits']);
        $this->assertSame(1, $stats['today_count']);
        $this->assertSame(1, $stats['monthly_count']);
        $this->assertSame(2, $stats['yearly_count']);

        Carbon::setTestNow();
    }
}
