<?php

namespace Tests\Feature;

use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsLeadPayload;
use Tests\TestCase;

class ApplyLeadRetentionPolicyTest extends TestCase
{
    use BuildsLeadPayload;
    use RefreshDatabase;

    private function makeLead(string $createdAt): Lead
    {
        Queue::fake();
        $this->postJson('/api/leads', $this->leadPayload());
        $lead = Lead::latest('id')->first();
        $lead->forceFill(['created_at' => $createdAt])->save();

        return $lead;
    }

    public function test_it_does_nothing_without_a_configured_window(): void
    {
        config(['leads.retention_days' => null]);
        $lead = $this->makeLead(now()->subYears(3)->toDateTimeString());

        $this->artisan('leads:apply-retention-policy')->assertSuccessful();

        $this->assertSame('Budi', $lead->fresh()->name);
    }

    public function test_it_anonymises_pii_past_the_window_but_keeps_funnel_columns(): void
    {
        config(['leads.retention_days' => 365]);
        $lead = $this->makeLead(now()->subYears(2)->toDateTimeString());

        $this->artisan('leads:apply-retention-policy')->assertSuccessful();

        $fresh = $lead->fresh();

        $this->assertSame('[anonymised]', $fresh->name);
        $this->assertSame('[anonymised]', $fresh->domicile);
        $this->assertSame('', $fresh->whatsapp_number);
        $this->assertStringStartsWith('anon-', $fresh->whatsapp_normalized);
        $this->assertNull($fresh->landing_page);

        // Reporting must survive the purge.
        $this->assertSame('instagram', $fresh->utm_source);
        $this->assertSame('basic', $fresh->program_interest);
        $this->assertNotNull($fresh->qualification);
        $this->assertNotNull($fresh->lead_code);
    }

    public function test_it_leaves_recent_leads_alone(): void
    {
        config(['leads.retention_days' => 365]);
        $lead = $this->makeLead(now()->subDays(30)->toDateTimeString());

        $this->artisan('leads:apply-retention-policy')->assertSuccessful();

        $this->assertSame('Budi', $lead->fresh()->name);
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        config(['leads.retention_days' => 365]);
        $lead = $this->makeLead(now()->subYears(2)->toDateTimeString());

        $this->artisan('leads:apply-retention-policy', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame('Budi', $lead->fresh()->name);
    }
}
