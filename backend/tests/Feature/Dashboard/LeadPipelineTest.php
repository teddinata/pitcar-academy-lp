<?php

namespace Tests\Feature\Dashboard;

use App\Enums\UserRole;
use App\Models\EducationConsultant;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsLeadPayload;
use Tests\TestCase;

class LeadPipelineTest extends TestCase
{
    use BuildsLeadPayload;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function makeLead(array $overrides = []): Lead
    {
        $this->postJson('/api/leads', $this->leadPayload($overrides));

        return Lead::latest('id')->firstOrFail();
    }

    public function test_a_status_change_is_recorded_with_who_made_it(): void
    {
        $user = User::factory()->create(['name' => 'Rina', 'role' => UserRole::Manager]);
        $this->actingAs($user);

        $lead = $this->makeLead();
        $historyBefore = $lead->statusHistories()->count();

        $lead->updatePipeline(['status' => 'contacted']);

        $history = $lead->statusHistories()->latest('id')->first();

        $this->assertSame($historyBefore + 1, $lead->statusHistories()->count());
        $this->assertSame('contacted', $history->to_status);
        $this->assertSame('Rina', $history->changed_by);
    }

    public function test_status_changes_keep_the_milestone_timestamps_in_step(): void
    {
        $lead = $this->makeLead();

        $lead->updatePipeline(['status' => 'contacted']);
        $this->assertNotNull($lead->fresh()->first_contacted_at);

        $lead->updatePipeline(['status' => 'qualified']);
        $this->assertNotNull($lead->fresh()->qualified_at);

        $lead->updatePipeline(['status' => 'converted']);
        $this->assertNotNull($lead->fresh()->converted_at);
    }

    public function test_marking_a_lead_lost_stamps_the_time_and_keeps_the_reason(): void
    {
        $lead = $this->makeLead();

        $lead->updatePipeline(['status' => 'lost', 'lost_reason' => 'Biaya di luar anggaran']);

        $fresh = $lead->fresh();
        $this->assertNotNull($fresh->lost_at);
        $this->assertSame('Biaya di luar anggaran', $fresh->lost_reason);
        $this->assertSame('Biaya di luar anggaran', $fresh->statusHistories()->latest('id')->first()->notes);
    }

    public function test_the_first_contact_timestamp_is_not_overwritten_on_later_contact(): void
    {
        $lead = $this->makeLead();

        $lead->updatePipeline(['status' => 'contacted']);
        $first = $lead->fresh()->first_contacted_at;

        $this->travel(2)->hours();
        $lead->updatePipeline(['status' => 'consultation']);
        $lead->updatePipeline(['status' => 'contacted']);

        $this->assertEquals($first, $lead->fresh()->first_contacted_at);
    }

    public function test_a_change_that_is_not_the_status_writes_no_history(): void
    {
        $lead = $this->makeLead();
        $before = $lead->statusHistories()->count();

        $lead->updatePipeline(['follow_up_due_at' => now()->addDay()]);

        $this->assertSame($before, $lead->fresh()->statusHistories()->count());
    }

    public function test_notes_belong_to_their_author(): void
    {
        $user = User::factory()->create(['role' => UserRole::Consultant]);
        $lead = $this->makeLead();

        $note = LeadNote::create([
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'note' => 'Sudah dihubungi, minta info cicilan.',
        ]);

        $this->assertSame($user->id, $note->author->id);
        $this->assertSame(1, $lead->notes()->count());
    }

    public function test_deleting_a_lead_takes_its_notes_with_it(): void
    {
        $lead = $this->makeLead();
        LeadNote::create(['lead_id' => $lead->id, 'note' => 'catatan']);

        $lead->forceDelete();

        $this->assertSame(0, LeadNote::count());
    }

    public function test_assigning_a_consultant_moves_a_new_lead_to_assigned(): void
    {
        config(['leads.fallback_consultant_whatsapp' => null]);
        $lead = $this->makeLead();
        $this->assertSame('new', $lead->status);

        $consultant = EducationConsultant::factory()->create();
        $lead->forceFill([
            'assigned_consultant_id' => $consultant->id,
            'assignment_reason' => 'manual_assignment',
            'assigned_at' => now(),
            'status' => 'assigned',
        ])->save();

        $this->assertSame('assigned', $lead->fresh()->status);
        $this->assertSame('manual_assignment', $lead->fresh()->assignment_reason);
        $this->assertSame('assigned', $lead->statusHistories()->latest('id')->first()->to_status);
    }
}
