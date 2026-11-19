<?php

namespace Tests\Feature\Api;

use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsLeadPayload;
use Tests\TestCase;

class LeadRateLimitTest extends TestCase
{
    use BuildsLeadPayload;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_it_throttles_repeated_leads_from_one_number(): void
    {
        config(['leads.rate_limit.per_whatsapp' => 2]);

        $this->postJson('/api/leads', $this->leadPayload())->assertCreated();
        $this->postJson('/api/leads', $this->leadPayload())->assertCreated();

        $this->postJson('/api/leads', $this->leadPayload())
            ->assertStatus(429)
            ->assertJsonStructure(['message']);

        $this->assertSame(2, Lead::count());
    }

    public function test_it_throttles_a_burst_from_one_ip(): void
    {
        config(['leads.rate_limit.per_ip' => 2, 'leads.rate_limit.per_whatsapp' => 100]);

        $this->postJson('/api/leads', $this->leadPayload(['whatsapp_number' => '081200000001']))->assertCreated();
        $this->postJson('/api/leads', $this->leadPayload(['whatsapp_number' => '081200000002']))->assertCreated();

        $this->postJson('/api/leads', $this->leadPayload(['whatsapp_number' => '081200000003']))
            ->assertStatus(429);
    }

    public function test_a_throttled_response_carries_retry_after(): void
    {
        config(['leads.rate_limit.per_ip' => 1]);

        $this->postJson('/api/leads', $this->leadPayload(['whatsapp_number' => '081200000001']))->assertCreated();

        $this->postJson('/api/leads', $this->leadPayload(['whatsapp_number' => '081200000002']))
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_throttling_one_number_does_not_block_another(): void
    {
        config(['leads.rate_limit.per_whatsapp' => 1]);

        $this->postJson('/api/leads', $this->leadPayload(['whatsapp_number' => '081211111111']))->assertCreated();
        $this->postJson('/api/leads', $this->leadPayload(['whatsapp_number' => '081211111111']))->assertStatus(429);

        $this->postJson('/api/leads', $this->leadPayload(['whatsapp_number' => '081222222222']))->assertCreated();
    }

    public function test_an_idempotent_retry_is_not_throttled_as_a_new_lead(): void
    {
        config(['leads.rate_limit.per_whatsapp' => 1]);

        $payload = $this->leadPayload();

        $this->postJson('/api/leads', $payload)->assertCreated();

        // The visitor pressing "coba simpan lagi" must not be locked out.
        $this->postJson('/api/leads', $payload)->assertOk();
        $this->postJson('/api/leads', $payload)->assertOk();

        $this->assertSame(1, Lead::count());
    }
}
