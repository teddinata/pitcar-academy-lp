<?php

namespace Tests\Feature;

use App\Jobs\NotifyNewLead;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsLeadPayload;
use Tests\TestCase;

class LeadWebhookTest extends TestCase
{
    use BuildsLeadPayload;
    use RefreshDatabase;

    private function storedLead(): Lead
    {
        $this->postJson('/api/leads', $this->leadPayload());

        return Lead::sole();
    }

    public function test_it_posts_the_lead_to_the_configured_webhook(): void
    {
        config(['leads.webhook_url' => 'https://workflows.example/wa-academy']);
        Http::fake(['*' => Http::response([], 200)]);

        $lead = $this->storedLead();
        (new NotifyNewLead($lead->id))->handle();

        Http::assertSent(function ($request) use ($lead) {
            return $request->url() === 'https://workflows.example/wa-academy'
                && $request['lead_code'] === $lead->lead_code
                && $request['phone'] === '6281234567890'
                && $request['city'] === 'Purwokerto'
                && $request['qualification'] === $lead->qualification;
        });
    }

    public function test_it_sends_nothing_when_no_webhook_is_configured(): void
    {
        config(['leads.webhook_url' => null]);
        Http::fake();

        (new NotifyNewLead($this->storedLead()->id))->handle();

        Http::assertNothingSent();
    }

    public function test_a_failing_webhook_does_not_lose_the_lead(): void
    {
        config(['leads.webhook_url' => 'https://workflows.example/wa-academy']);
        Http::fake(['*' => Http::response('nope', 500)]);

        $lead = $this->storedLead();

        // The job throws so the queue retries it; the lead is already committed.
        try {
            (new NotifyNewLead($lead->id))->handle();
            $this->fail('a 500 from the webhook should surface to the queue');
        } catch (\Throwable) {
            // expected
        }

        $this->assertSame(1, Lead::count());
        $this->assertNotNull(Lead::sole()->lead_code);
    }

    public function test_the_api_still_returns_201_when_the_webhook_is_down(): void
    {
        config(['leads.webhook_url' => 'https://workflows.example/wa-academy']);
        Http::fake(['*' => Http::response('nope', 500)]);

        // Queued after commit, so the visitor never waits on it and never sees
        // it fail.
        $this->postJson('/api/leads', $this->leadPayload())->assertCreated();
        $this->assertSame(1, Lead::count());
    }
}
