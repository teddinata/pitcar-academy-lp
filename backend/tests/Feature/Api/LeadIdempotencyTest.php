<?php

namespace Tests\Feature\Api;

use App\Jobs\NotifyNewLead;
use App\Models\EducationConsultant;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsLeadPayload;
use Tests\TestCase;

class LeadIdempotencyTest extends TestCase
{
    use BuildsLeadPayload;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        EducationConsultant::factory()->create(['whatsapp_number' => '628111222333']);
    }

    public function test_replaying_the_same_submission_id_does_not_create_a_second_lead(): void
    {
        $payload = $this->leadPayload();

        $first = $this->postJson('/api/leads', $payload)->assertCreated();
        $second = $this->postJson('/api/leads', $payload)->assertOk();

        $this->assertSame(1, Lead::count());
        $this->assertSame($first->json('lead_code'), $second->json('lead_code'));
        $this->assertSame($first->json('score'), $second->json('score'));
        $this->assertSame($first->json('qualification'), $second->json('qualification'));
    }

    public function test_a_replay_does_not_notify_twice(): void
    {
        $payload = $this->leadPayload();

        $this->postJson('/api/leads', $payload)->assertCreated();
        $this->postJson('/api/leads', $payload)->assertOk();

        Queue::assertPushed(NotifyNewLead::class, 1);
    }

    public function test_a_replay_records_the_extra_attempt(): void
    {
        $payload = $this->leadPayload();

        $this->postJson('/api/leads', $payload);
        $this->postJson('/api/leads', $payload);
        $this->postJson('/api/leads', $payload);

        $this->assertSame(3, Lead::sole()->submission_count);
        $this->assertNotNull(Lead::sole()->last_submitted_at);
    }

    public function test_a_replay_does_not_rescore_or_reassign(): void
    {
        $payload = $this->leadPayload();

        $this->postJson('/api/leads', $payload)->assertCreated();
        $original = Lead::sole()->only(['score', 'qualification', 'assigned_consultant_id', 'lead_code', 'scored_at']);

        // Even if the visitor edits their answers, the stored lead stands; the
        // consultant follows up on what was actually recorded.
        $this->postJson('/api/leads', array_merge($payload, [
            'readiness' => 'exploring',
        ]))->assertOk();

        $this->assertSame(1, Lead::count());
        $this->assertEquals($original, Lead::sole()->only(array_keys($original)));
        $this->assertSame(1, Lead::sole()->statusHistories()->count());
    }

    public function test_a_different_submission_id_creates_a_separate_lead_with_a_new_code(): void
    {
        $first = $this->postJson('/api/leads', $this->leadPayload())->assertCreated();
        $second = $this->postJson('/api/leads', $this->leadPayload())->assertCreated();

        $this->assertSame(2, Lead::count());
        $this->assertNotSame($first->json('lead_code'), $second->json('lead_code'));
    }

    public function test_lead_codes_increment_without_gaps_or_collisions(): void
    {
        $codes = [];

        for ($i = 0; $i < 5; $i++) {
            // Distinct numbers: the per-number limit is about distinct leads.
            $codes[] = $this->postJson('/api/leads', $this->leadPayload([
                'whatsapp_number' => '08123456'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            ]))->json('lead_code');
        }

        $year = now()->year;
        $this->assertSame([
            "PA-{$year}-000001",
            "PA-{$year}-000002",
            "PA-{$year}-000003",
            "PA-{$year}-000004",
            "PA-{$year}-000005",
        ], $codes);
    }

    public function test_a_returning_number_keeps_the_same_consultant(): void
    {
        $busy = EducationConsultant::factory()->create(['priority' => 1, 'whatsapp_number' => '628444555666']);

        $this->postJson('/api/leads', $this->leadPayload())->assertCreated();
        $firstConsultant = Lead::latest('id')->first()->assigned_consultant_id;
        $this->assertSame($busy->id, $firstConsultant);

        $busy->update(['is_active' => false]);

        $this->postJson('/api/leads', $this->leadPayload())->assertCreated();

        $this->assertSame($firstConsultant, Lead::latest('id')->first()->assigned_consultant_id);
        $this->assertSame('returning_lead_same_consultant', Lead::latest('id')->first()->assignment_reason);
    }
}
