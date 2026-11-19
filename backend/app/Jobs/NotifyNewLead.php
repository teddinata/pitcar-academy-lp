<?php

namespace App\Jobs;

use App\Models\Lead;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Hands a stored lead to the outside world: the Cekat AI webhook that drives
 * WhatsApp automation, plus an internal log line.
 *
 * Takes the lead id rather than the model so a retry always reads current
 * state. Runs on the queue, after the transaction commits — a webhook that is
 * slow or down must never cost a lead that is already saved.
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

        $this->sendWebhook($lead);

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

    private function sendWebhook(Lead $lead): void
    {
        $url = config('leads.webhook_url');

        if (blank($url)) {
            return;
        }

        $response = Http::timeout((int) config('leads.webhook_timeout'))
            ->asJson()
            ->post($url, [
                'lead_code' => $lead->lead_code,
                'name' => $lead->name,
                'phone' => $lead->whatsapp_normalized,
                'city' => $lead->domicile,
                'program_interest' => $lead->program_interest,
                'goal' => $lead->goal,
                'readiness' => $lead->readiness,
                'score' => $lead->score,
                'qualification' => $lead->qualification,
                'source_cta' => $lead->source_cta,
                'utm_source' => $lead->utm_source,
                'utm_campaign' => $lead->utm_campaign,
                'created_at' => $lead->created_at?->toIso8601String(),
            ]);

        // Throwing here lets the queue retry with backoff. The lead itself is
        // already committed, so nothing is lost while that happens.
        $response->throw();
    }
}
