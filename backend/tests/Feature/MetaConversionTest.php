<?php

namespace Tests\Feature;

use App\Jobs\SendMetaConversion;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsLeadPayload;
use Tests\TestCase;

/**
 * The queue runs inline under `sync` here, so posting a lead exercises the
 * real path: intake commits, then the job reports to Meta.
 */
class MetaConversionTest extends TestCase
{
    use BuildsLeadPayload;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.meta.pixel_id' => '1364110365687038',
            'services.meta.access_token' => 'test-token',
            'services.meta.graph_version' => 'v21.0',
            'services.meta.test_event_code' => null,
            // NotifyNewLead shares this path; silencing it keeps the recorded
            // requests to the one under test.
            'leads.webhook_url' => null,
        ]);
    }

    private function fakeGraph(int $status = 200): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(
            $status === 200 ? ['events_received' => 1] : ['error' => ['message' => 'Invalid token']],
            $status,
        )]);
    }

    /**
     * @return array<string, mixed>
     */
    private function graphPayload(): array
    {
        $recorded = Http::recorded();

        $this->assertNotEmpty($recorded, 'No request reached the Graph API.');

        return $recorded->first()[0]->data();
    }

    public function test_it_hashes_personal_data_before_it_leaves_the_server(): void
    {
        $this->fakeGraph();

        $this->postJson('/api/leads', $this->leadPayload())->assertCreated();
        $lead = Lead::sole();

        $payload = $this->graphPayload();
        $user = $payload['data'][0]['user_data'];

        $this->assertSame(hash('sha256', $lead->whatsapp_normalized), $user['ph']);
        $this->assertSame(hash('sha256', 'budi'), $user['fn']);
        $this->assertSame(hash('sha256', 'purwokerto'), $user['ct']);

        // A digest matches just as well as the plain value, so the plain value
        // has no reason to cross to a third party at all.
        $encoded = json_encode($payload);
        $this->assertStringNotContainsString($lead->whatsapp_normalized, $encoded);
        $this->assertStringNotContainsString('Purwokerto', $encoded);
    }

    public function test_it_reuses_the_submission_id_so_meta_dedupes_the_browser_event(): void
    {
        $this->fakeGraph();

        $this->postJson('/api/leads', $this->leadPayload())->assertCreated();
        $lead = Lead::sole();

        $event = $this->graphPayload()['data'][0];

        $this->assertSame($lead->submission_id, $event['event_id']);
        $this->assertSame('Lead', $event['event_name']);
        $this->assertSame('website', $event['action_source']);
        $this->assertSame($lead->score, $event['custom_data']['lead_score']);
    }

    public function test_it_forwards_the_meta_cookies_unhashed(): void
    {
        $this->fakeGraph();

        $payload = $this->leadPayload();
        $payload['attribution']['fbp'] = 'fb.1.1700000000000.1234567890';
        $payload['attribution']['fbc'] = 'fb.1.1700000000000.IwAR0abcdef';

        $this->postJson('/api/leads', $payload)->assertCreated();

        $user = $this->graphPayload()['data'][0]['user_data'];

        // Meta's spec: these two are matched verbatim, not as digests.
        $this->assertSame('fb.1.1700000000000.1234567890', $user['fbp']);
        $this->assertSame('fb.1.1700000000000.IwAR0abcdef', $user['fbc']);
    }

    public function test_it_does_nothing_when_no_token_is_configured(): void
    {
        config(['services.meta.access_token' => null]);
        Http::fake();

        $this->postJson('/api/leads', $this->leadPayload())->assertCreated();

        Http::assertNothingSent();
        $this->assertNull(Lead::sole()->meta_conversion_sent_at);
    }

    public function test_it_does_not_report_the_same_lead_twice(): void
    {
        $this->fakeGraph();

        $this->postJson('/api/leads', $this->leadPayload())->assertCreated();
        $lead = Lead::sole();

        $this->assertNotNull($lead->fresh()->meta_conversion_sent_at);

        // A retry, or a second dispatch, must not become a second conversion.
        SendMetaConversion::dispatchSync($lead->id);

        Http::assertSentCount(1);
    }

    public function test_a_failing_meta_call_never_costs_the_lead(): void
    {
        $this->fakeGraph(400);

        // Inline queue: an unguarded exception here would surface to the
        // visitor as a lost submission for a lead that is already committed.
        $this->postJson('/api/leads', $this->leadPayload())
            ->assertCreated()
            ->assertJsonPath('lead_code', fn (string $code) => str_starts_with($code, 'PA-'));

        $this->assertSame(1, Lead::count());
        $this->assertNull(Lead::sole()->meta_conversion_sent_at);
    }
}
