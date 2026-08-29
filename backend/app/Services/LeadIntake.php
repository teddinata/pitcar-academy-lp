<?php

namespace App\Services;

use App\Jobs\NotifyNewLead;
use App\Models\Lead;
use App\Models\LeadStatusHistory;
use App\Support\WhatsAppNumber;
use Illuminate\Support\Facades\DB;

class LeadIntake
{
    public function __construct(
        private readonly LeadScorer $scorer,
        private readonly LeadCodeGenerator $codes,
        private readonly ConsultantRouter $router,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated request payload.
     * @return array{lead: Lead, created: bool}
     */
    public function handle(array $data): array
    {
        $result = DB::transaction(fn () => $this->store($data));

        // Integrations run after the row is committed. A failing CRM or
        // notification must never cost us a lead that is already saved.
        if ($result['created']) {
            NotifyNewLead::dispatch($result['lead']->id);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{lead: Lead, created: bool}
     */
    private function store(array $data): array
    {
        $existing = Lead::query()
            ->where('submission_id', $data['submission_id'])
            ->lockForUpdate()
            ->first();

        // Idempotent replay: a retried submission returns the lead that already
        // exists, with the same lead code, and re-runs neither scoring nor the
        // notification job.
        if ($existing !== null) {
            $existing->forceFill([
                'last_submitted_at' => now(),
                'submission_count' => $existing->submission_count + 1,
            ])->save();

            return ['lead' => $existing, 'created' => false];
        }

        $normalized = WhatsAppNumber::normalize($data['whatsapp_number']);
        $attribution = $data['attribution'] ?? [];

        $scoring = $this->scorer->score($data);
        [$consultantId, $assignmentReason] = $this->resolveConsultant($data, $normalized);

        $lead = new Lead;
        $lead->fill([
            'submission_id' => $data['submission_id'],
            'name' => $data['name'],
            'whatsapp_number' => $data['whatsapp_number'],
            'whatsapp_normalized' => $normalized,
            'domicile' => $data['domicile'],
            'activity' => $data['activity'],
            'goal' => $data['goal'],
            'timeline' => $data['timeline'],
            'investment_readiness' => $data['investment_readiness'],
            'program_interest' => $data['program_interest'],
            'source' => $data['source'] ?? 'website',
            'source_cta' => $data['source_cta'],
            'consent_at' => $data['consent_at'],
            'landing_page' => $attribution['landing_page'] ?? null,
            'referrer' => $attribution['referrer'] ?? null,
            'utm_source' => $attribution['utm_source'] ?? null,
            'utm_medium' => $attribution['utm_medium'] ?? null,
            'utm_campaign' => $attribution['utm_campaign'] ?? null,
            'utm_content' => $attribution['utm_content'] ?? null,
            'utm_term' => $attribution['utm_term'] ?? null,
        ]);

        // forceFill, not fill: none of these are mass assignable, which is what
        // stops a request from posting its own score or lead_code.
        $lead->forceFill([
            'lead_code' => $this->codes->next(),
            'score' => $scoring['score'],
            'qualification' => $scoring['qualification'],
            'scoring_reasons' => $scoring['reasons'],
            'scoring_version' => $scoring['version'],
            'scored_at' => $scoring['scored_at'],
            'status' => $consultantId ? 'assigned' : 'new',
            'assigned_consultant_id' => $consultantId,
            'assignment_reason' => $assignmentReason,
            'assigned_at' => $consultantId ? now() : null,
            'follow_up_due_at' => now()->addHours($this->slaHours($scoring['qualification'])),
            'last_submitted_at' => now(),
            'submission_count' => 1,
        ])->save();

        LeadStatusHistory::create([
            'lead_id' => $lead->id,
            'from_status' => null,
            'to_status' => $lead->status,
            'changed_by' => 'system',
            'notes' => 'Lead masuk dari '.$lead->source_cta.' ('.$assignmentReason.')',
        ]);

        return ['lead' => $lead->fresh('consultant'), 'created' => true];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: ?int, 1: string}
     */
    private function resolveConsultant(array $data, ?string $normalized): array
    {
        // Someone who submits twice should keep talking to the same person
        // rather than starting over with whoever is next in the rotation.
        if ($normalized !== null) {
            $previous = Lead::query()
                ->where('whatsapp_normalized', $normalized)
                ->whereNotNull('assigned_consultant_id')
                ->where('created_at', '>=', now()->subDays(30))
                ->latest('id')
                ->first();

            if ($previous !== null) {
                return [$previous->assigned_consultant_id, 'returning_lead_same_consultant'];
            }
        }

        $routing = $this->router->route($data['program_interest'], $data['domicile']);

        return [$routing['consultant']?->id, $routing['reason']];
    }

    private function slaHours(string $qualification): int
    {
        return match ($qualification) {
            'hot' => 1,
            'qualified' => 4,
            'nurture' => 24,
            default => 48,
        };
    }
}
