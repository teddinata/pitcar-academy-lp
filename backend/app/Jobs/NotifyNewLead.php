<?php

namespace App\Jobs;

use App\Models\Lead;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Placeholder for the internal alert (Slack / WhatsApp / CRM sync). Takes the
 * lead id rather than the model so a retry always reads current state, and
 * logs no PII beyond the lead code.
 */
class NotifyNewLead implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 300];

    public function __construct(public readonly int $leadId) {}

    public function handle(): void
    {
        $lead = Lead::find($this->leadId);

        if ($lead === null) {
            return;
        }

        Log::channel(config('logging.default'))->info('lead.created', [
            'lead_code' => $lead->lead_code,
            'qualification' => $lead->qualification,
            'score' => $lead->score,
            'program_interest' => $lead->program_interest,
            'source_cta' => $lead->source_cta,
            'utm_source' => $lead->utm_source,
            'assigned_consultant_id' => $lead->assigned_consultant_id,
        ]);
    }
}
