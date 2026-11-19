<?php

namespace Tests\Feature\Api;

use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsLeadPayload;
use Tests\TestCase;

class LeadValidationTest extends TestCase
{
    use BuildsLeadPayload;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidPayloads(): array
    {
        return [
            'missing name' => [['name' => null], 'name'],
            'name too long' => [['name' => str_repeat('a', 101)], 'name'],
            'missing whatsapp' => [['whatsapp_number' => null], 'whatsapp_number'],
            'whatsapp not indonesian' => [['whatsapp_number' => '+1 415 555 2671'], 'whatsapp_number'],
            'whatsapp too short' => [['whatsapp_number' => '0812345'], 'whatsapp_number'],
            'whatsapp letters' => [['whatsapp_number' => 'not-a-number'], 'whatsapp_number'],
            'missing domicile' => [['domicile' => ''], 'domicile'],
            'unknown goal' => [['goal' => 'world_domination'], 'goal'],
            'missing readiness' => [['readiness' => null], 'readiness'],
            'unknown readiness' => [['readiness' => 'someday'], 'readiness'],
            'unknown legacy activity' => [['activity' => 'astronaut'], 'activity'],
            'unknown legacy timeline' => [['timeline' => 'someday'], 'timeline'],
            'unknown program' => [['program_interest' => 'phd'], 'program_interest'],
            'unknown source' => [['source' => 'carrier_pigeon'], 'source'],
            'missing source cta' => [['source_cta' => null], 'source_cta'],
            'submission id too short' => [['submission_id' => 'abc'], 'submission_id'],
            'submission id with unsafe characters' => [['submission_id' => 'web/../../etc/passwd'], 'submission_id'],
            'consent in the future' => [['consent_at' => '2099-01-01T00:00:00Z'], 'consent_at'],
            'consent long past' => [['consent_at' => '2020-01-01T00:00:00Z'], 'consent_at'],
            'consent not a date' => [['consent_at' => 'yesterday-ish'], 'consent_at'],
            'landing page not a url' => [['attribution' => ['landing_page' => 'not a url']], 'attribution.landing_page'],
            'referrer not a url' => [['attribution' => ['referrer' => 'javascript:alert(1)']], 'attribution.referrer'],
            'utm too long' => [['attribution' => ['utm_source' => str_repeat('a', 256)]], 'attribution.utm_source'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidPayloads')]
    public function test_it_rejects_invalid_payloads(array $overrides, string $field): void
    {
        $this->postJson('/api/leads', $this->leadPayload($overrides))
            ->assertStatus(422)
            ->assertJsonValidationErrors($field);

        $this->assertSame(0, Lead::count());
    }

    public function test_it_still_accepts_the_retired_fields_from_an_old_client(): void
    {
        // A cached landing page must keep working while it is still in the wild.
        $this->postJson('/api/leads', $this->leadPayload([
            'activity' => 'job_seeker',
            'timeline' => 'nearest_batch',
            'investment_readiness' => 'ready',
        ]))->assertCreated();

        $lead = Lead::sole();
        $this->assertSame('job_seeker', $lead->activity);
        $this->assertSame('nearest_batch', $lead->timeline);
    }

    public function test_the_short_form_payload_needs_no_activity_or_timeline(): void
    {
        $this->postJson('/api/leads', $this->leadPayload())->assertCreated();

        $lead = Lead::sole();
        $this->assertNull($lead->activity);
        $this->assertNull($lead->timeline);
        $this->assertNotNull($lead->readiness);
    }

    public function test_it_rejects_a_missing_attribution_object(): void
    {
        $payload = $this->leadPayload();
        unset($payload['attribution']);

        $this->postJson('/api/leads', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('attribution');
    }

    public function test_it_returns_the_documented_error_envelope(): void
    {
        $this->postJson('/api/leads', $this->leadPayload(['whatsapp_number' => 'nope']))
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['whatsapp_number']]);
    }

    public function test_it_rejects_an_oversized_payload_before_validation(): void
    {
        $payload = $this->leadPayload();

        $this->call(
            'POST',
            '/api/leads',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_LENGTH' => (string) (config('leads.max_payload_bytes') + 1),
            ],
            content: json_encode($payload)
        )->assertStatus(413);

        $this->assertSame(0, Lead::count());
    }
}
