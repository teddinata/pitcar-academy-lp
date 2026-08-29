<?php

namespace Tests\Feature\Api;

use App\Jobs\NotifyNewLead;
use App\Models\EducationConsultant;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsLeadPayload;
use Tests\TestCase;

class StoreLeadTest extends TestCase
{
    use BuildsLeadPayload;
    use RefreshDatabase;

    public function test_it_stores_a_lead_and_returns_the_documented_shape(): void
    {
        Queue::fake();
        EducationConsultant::factory()->create(['whatsapp_number' => '628111222333']);

        $response = $this->postJson('/api/leads', $this->leadPayload());

        $response->assertCreated()
            ->assertJsonStructure(['lead_code', 'score', 'qualification', 'whatsapp_url', 'message']);

        $lead = Lead::sole();

        $this->assertSame($response->json('lead_code'), $lead->lead_code);
        $this->assertMatchesRegularExpression('/^PA-\d{4}-\d{6}$/', $lead->lead_code);
        $this->assertSame('Budi', $lead->name);
        $this->assertSame('Purwokerto', $lead->domicile);
        $this->assertSame('instagram', $lead->utm_source);
        $this->assertSame('batch_october', $lead->utm_campaign);
        $this->assertSame('package_basic', $lead->source_cta);
        $this->assertNotNull($lead->consent_at);
    }

    public function test_it_normalises_the_whatsapp_number_server_side(): void
    {
        Queue::fake();

        $this->postJson('/api/leads', $this->leadPayload(['whatsapp_number' => '+62 812-3456-7890']))
            ->assertCreated();

        $lead = Lead::sole();

        $this->assertSame('6281234567890', $lead->whatsapp_normalized);
        $this->assertSame('+62 812-3456-7890', $lead->whatsapp_number, 'the raw input is kept for reference');
    }

    public function test_it_scores_and_qualifies_the_lead_on_the_server(): void
    {
        Queue::fake();

        $this->postJson('/api/leads', $this->leadPayload([
            'timeline' => 'nearest_batch',          // 25
            'investment_readiness' => 'ready',      // 25
            'goal' => 'mechanic_career',            // 20
            'activity' => 'mechanic',               // 15
            'program_interest' => 'basic',          // 10
        ]))->assertCreated()
            ->assertJsonPath('score', 95)
            ->assertJsonPath('qualification', 'hot');

        $lead = Lead::sole();

        $this->assertSame('2026-01', $lead->scoring_version);
        $this->assertNotNull($lead->scored_at);
        $this->assertCount(5, $lead->scoring_reasons);
        $this->assertContains(
            ['rule' => 'timeline', 'value' => 'nearest_batch', 'points' => 25],
            $lead->scoring_reasons
        );
    }

    public function test_it_returns_a_whatsapp_url_for_the_routed_consultant(): void
    {
        Queue::fake();
        $consultant = EducationConsultant::factory()->create(['whatsapp_number' => '628111222333']);

        $response = $this->postJson('/api/leads', $this->leadPayload())->assertCreated();

        $url = $response->json('whatsapp_url');

        $this->assertStringStartsWith('https://wa.me/628111222333?text=', $url);
        $this->assertSame('https', parse_url($url, PHP_URL_SCHEME));
        $this->assertSame('wa.me', parse_url($url, PHP_URL_HOST));

        $message = urldecode((string) parse_url($url, PHP_URL_QUERY));
        $this->assertStringContainsString(Lead::sole()->lead_code, $message);
        $this->assertStringContainsString('Budi', $message);
        $this->assertStringNotContainsString('score', strtolower($message));

        $this->assertSame($consultant->id, Lead::sole()->assigned_consultant_id);
        $this->assertSame('assigned', Lead::sole()->status);
    }

    public function test_it_still_stores_the_lead_when_no_consultant_is_configured(): void
    {
        Queue::fake();
        config(['leads.fallback_consultant_whatsapp' => null]);

        $response = $this->postJson('/api/leads', $this->leadPayload())->assertCreated();

        $this->assertNull($response->json('whatsapp_url'));
        $this->assertNotNull($response->json('lead_code'));

        $lead = Lead::sole();
        $this->assertNull($lead->assigned_consultant_id);
        $this->assertSame('new', $lead->status);
        $this->assertSame('no_consultant_configured', $lead->assignment_reason);
    }

    public function test_it_falls_back_to_the_configured_number_when_no_consultant_row_matches(): void
    {
        Queue::fake();
        config(['leads.fallback_consultant_whatsapp' => '628999888777']);

        $response = $this->postJson('/api/leads', $this->leadPayload())->assertCreated();

        $this->assertStringStartsWith('https://wa.me/628999888777?text=', (string) $response->json('whatsapp_url'));
    }

    public function test_it_records_the_intake_in_the_status_history(): void
    {
        Queue::fake();
        EducationConsultant::factory()->create();

        $this->postJson('/api/leads', $this->leadPayload())->assertCreated();

        $history = Lead::sole()->statusHistories()->sole();

        $this->assertNull($history->from_status);
        $this->assertSame('assigned', $history->to_status);
        $this->assertSame('system', $history->changed_by);
    }

    public function test_it_dispatches_the_notification_after_the_lead_is_stored(): void
    {
        Queue::fake();

        $this->postJson('/api/leads', $this->leadPayload())->assertCreated();

        Queue::assertPushed(NotifyNewLead::class, 1);
    }
}
