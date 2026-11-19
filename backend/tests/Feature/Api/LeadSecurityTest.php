<?php

namespace Tests\Feature\Api;

use App\Models\EducationConsultant;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsLeadPayload;
use Tests\TestCase;

class LeadSecurityTest extends TestCase
{
    use BuildsLeadPayload;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_a_client_cannot_set_its_own_score_or_pipeline_state(): void
    {
        $consultant = EducationConsultant::factory()->create();
        $other = EducationConsultant::factory()->create();

        $this->postJson('/api/leads', $this->leadPayload([
            'score' => 100,
            'qualification' => 'hot',
            'status' => 'converted',
            'lead_code' => 'PA-1999-000001',
            'assigned_consultant_id' => $other->id,
            'scoring_version' => 'forged',
            'whatsapp_normalized' => '628000000000',
        ]))->assertCreated();

        $lead = Lead::sole();

        $this->assertNotSame(100, $lead->score);
        $this->assertNotSame('PA-1999-000001', $lead->lead_code);
        $this->assertNotSame('converted', $lead->status);
        $this->assertSame('2026-03', $lead->scoring_version);
        $this->assertSame('6281234567890', $lead->whatsapp_normalized);
        $this->assertSame($consultant->id, $lead->assigned_consultant_id);
    }

    public function test_pipeline_fields_stay_guarded_against_mass_assignment(): void
    {
        $this->postJson('/api/leads', $this->leadPayload())->assertCreated();
        $lead = Lead::sole();

        // update() is the path any careless future code would reach for; the
        // dashboard has to go through updatePipeline() on purpose.
        $lead->update(['status' => 'converted', 'score' => 100, 'lead_code' => 'PA-1999-000001']);

        $fresh = $lead->fresh();
        $this->assertNotSame('converted', $fresh->status);
        $this->assertNotSame(100, $fresh->score);
        $this->assertNotSame('PA-1999-000001', $fresh->lead_code);
    }

    public function test_the_response_never_leaks_internal_fields(): void
    {
        $response = $this->postJson('/api/leads', $this->leadPayload())->assertCreated();

        $this->assertSame(
            ['lead_code', 'score', 'qualification', 'whatsapp_url', 'message'],
            array_keys($response->json())
        );
    }

    public function test_it_accepts_a_preflight_from_an_allowed_origin(): void
    {
        config(['cors.allowed_origins' => ['https://academy.pitcar.co.id']]);

        $this->call('OPTIONS', '/api/leads', server: [
            'HTTP_ORIGIN' => 'https://academy.pitcar.co.id',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type,x-submission-id',
        ])->assertSuccessful()
            ->assertHeader('Access-Control-Allow-Origin', 'https://academy.pitcar.co.id');
    }

    public function test_it_does_not_grant_cors_access_to_a_foreign_origin(): void
    {
        config(['cors.allowed_origins' => [
            'https://academy.pitcar.co.id',
            'https://staging-academy.pitcar.co.id',
        ]]);

        $response = $this->call('OPTIONS', '/api/leads', server: [
            'HTTP_ORIGIN' => 'https://evil.example.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);

        $this->assertNotSame(
            'https://evil.example.com',
            $response->headers->get('Access-Control-Allow-Origin')
        );
    }

    public function test_the_endpoint_does_not_depend_on_a_session_cookie(): void
    {
        $response = $this->postJson('/api/leads', $this->leadPayload())->assertCreated();

        $this->assertEmpty(array_filter(
            $response->baseResponse->headers->getCookies(),
            fn ($cookie) => $cookie->getName() === config('session.cookie')
        ));
    }
}
