<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Support\LeadCsvExporter;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsLeadPayload;
use Tests\TestCase;

/**
 * Timestamps are stored in UTC. Everyone reading them works in WIB, so the
 * panel and the export have to convert — a seven-hour gap makes every
 * follow-up SLA look breached, or not yet due, when the opposite is true.
 */
class DisplayTimezoneTest extends TestCase
{
    use BuildsLeadPayload;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_it_keeps_storing_utc(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
    }

    public function test_the_panel_renders_in_the_display_timezone(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.display_timezone'));
        $this->assertSame('Asia/Jakarta', FilamentTimezone::get());
    }

    public function test_the_csv_export_writes_wall_clock_time_for_its_reader(): void
    {
        $this->postJson('/api/leads', $this->leadPayload());

        // 09:55 UTC, the moment this surfaced in production. 16:55 in Jakarta.
        Lead::sole()->forceFill([
            'created_at' => Carbon::parse('2026-09-01 09:55:00', 'UTC'),
        ])->save();

        $response = app(LeadCsvExporter::class)->stream(Lead::query(), 'test.csv');

        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        // A spreadsheet carries no timezone, so the column has to already be
        // local. Leaving UTC in it reads as local and is quietly seven hours
        // out.
        $this->assertStringContainsString('2026-09-01 16:55:00', $csv);
        $this->assertStringNotContainsString('2026-09-01 09:55:00', $csv);
    }
}
