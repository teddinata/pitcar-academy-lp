<?php

namespace Tests\Feature\Dashboard;

use App\Models\EducationConsultant;
use App\Models\Lead;
use App\Services\WhatsAppLinkBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsLeadPayload;
use Tests\TestCase;

/**
 * Two links point in opposite directions and must never be swapped: the
 * visitor's link opens a chat with the consultant, the dashboard's opens a
 * chat with the lead.
 */
class FollowUpLinkTest extends TestCase
{
    use BuildsLeadPayload;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function lead(array $overrides = []): Lead
    {
        $this->postJson('/api/leads', $this->leadPayload($overrides));

        return Lead::sole();
    }

    public function test_it_addresses_the_lead_and_not_the_consultant(): void
    {
        $consultant = EducationConsultant::factory()->create([
            'name' => 'Hanif Adha Bijaksana Wibowo',
            'whatsapp_number' => '6281111111111',
            'is_active' => true,
        ]);

        $lead = $this->lead();
        $lead->forceFill(['assigned_consultant_id' => $consultant->id])->save();

        $link = app(WhatsAppLinkBuilder::class)->buildFollowUp($lead->fresh());

        $this->assertStringContainsString('wa.me/'.$lead->whatsapp_normalized, $link);
        $this->assertStringNotContainsString('wa.me/6281111111111', $link);
    }

    public function test_the_message_is_written_by_the_consultant(): void
    {
        $consultant = EducationConsultant::factory()->create([
            'name' => 'Hanif Adha Bijaksana Wibowo',
            'is_active' => true,
        ]);

        $lead = $this->lead();
        $lead->forceFill(['assigned_consultant_id' => $consultant->id])->save();

        $message = app(WhatsAppLinkBuilder::class)->followUpMessage($lead->fresh());

        $this->assertStringContainsString('Halo Budi', $message);
        $this->assertStringContainsString('saya Hanif dari Pitcar Academy', $message);
        // The student's own wording must not leak into the consultant's mouth.
        $this->assertStringNotContainsString('saya sudah mengisi form konsultasi', $message);

        // Blank lines are the paragraph breaks WhatsApp renders. Filtering
        // them out once collapsed the whole message into a wall of text.
        $this->assertStringContainsString("\n\n", $message);
    }

    public function test_it_opens_on_what_the_visitor_said_they_needed(): void
    {
        $builder = app(WhatsAppLinkBuilder::class);

        $lead = $this->lead(['readiness' => 'family_discussion']);
        $this->assertStringContainsString('orang tua', $builder->followUpMessage($lead));

        Lead::query()->delete();

        $lead = $this->lead(['readiness' => 'need_payment_plan', 'submission_id' => 'other-submission-0002']);
        $this->assertStringContainsString('opsi pembayaran', $builder->followUpMessage($lead));
    }

    public function test_it_still_works_before_a_consultant_is_assigned(): void
    {
        $lead = $this->lead();
        $lead->forceFill(['assigned_consultant_id' => null])->save();

        $message = app(WhatsAppLinkBuilder::class)->followUpMessage($lead->fresh());

        $this->assertStringContainsString('saya dari Pitcar Academy', $message);
        $this->assertNotNull(app(WhatsAppLinkBuilder::class)->buildFollowUp($lead->fresh()));
    }

    public function test_the_visitor_link_still_points_at_the_consultant(): void
    {
        $consultant = EducationConsultant::factory()->create([
            'whatsapp_number' => '6281111111111',
            'is_active' => true,
        ]);

        $lead = $this->lead();
        $lead->forceFill(['assigned_consultant_id' => $consultant->id])->save();

        $link = app(WhatsAppLinkBuilder::class)->build($lead->fresh());

        $this->assertStringContainsString('wa.me/6281111111111', $link);
    }
}
