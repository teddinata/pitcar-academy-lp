<?php

namespace App\Jobs;

use App\Models\Lead;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Reports the lead to Meta's Conversions API.
 *
 * Server-side because the browser pixel is lost to ad blockers, ITP, and any
 * visitor who closes the tab before the beacon flushes. The same event is
 * still sent from the browser; `event_id` is the visitor's own submission_id,
 * so Meta collapses the pair into one conversion instead of counting two.
 *
 * Personal data is hashed before it leaves the server — that is Meta's
 * requirement and ours: a plain phone number has no business crossing to a
 * third party when a SHA-256 digest matches just as well.
 */
class SendMetaConversion implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 300];

    public function __construct(public readonly int $leadId) {}

    public function handle(): void
    {
        $pixelId = config('services.meta.pixel_id');
        $token = config('services.meta.access_token');

        // Not configured is a valid state, not a failure: the site runs fine
        // without ad reporting, and retrying forever would only fill the log.
        if (blank($pixelId) || blank($token)) {
            return;
        }

        $lead = Lead::find($this->leadId);

        if ($lead === null || $lead->meta_conversion_sent_at !== null) {
            return;
        }

        $response = Http::timeout((int) config('services.meta.timeout'))
            ->asJson()
            ->post(
                sprintf(
                    'https://graph.facebook.com/%s/%s/events',
                    config('services.meta.graph_version'),
                    $pixelId,
                ),
                array_filter([
                    'access_token' => $token,
                    'test_event_code' => config('services.meta.test_event_code'),
                    'data' => [$this->event($lead)],
                ]),
            );

        if ($response->failed()) {
            Log::warning('lead.meta_conversion_failed', [
                'lead_code' => $lead->lead_code,
                'status' => $response->status(),
                // Meta explains rejections in the body; without it every
                // failure looks identical and is impossible to act on.
                'error' => $response->json('error.message'),
            ]);
        }

        $response->throw();

        $lead->forceFill(['meta_conversion_sent_at' => now()])->saveQuietly();
    }

    /**
     * @return array<string, mixed>
     */
    private function event(Lead $lead): array
    {
        return array_filter([
            'event_name' => 'Lead',
            'event_time' => $lead->created_at?->getTimestamp() ?? now()->getTimestamp(),
            // Shared with the browser pixel so the two are deduplicated.
            'event_id' => $lead->submission_id,
            'event_source_url' => $lead->landing_page,
            'action_source' => 'website',
            'user_data' => $this->userData($lead),
            'custom_data' => array_filter([
                'content_name' => $lead->program_interest,
                'lead_event_source' => 'pitcar_academy_lead_api',
                // Lets Meta optimise toward the leads sales actually wants
                // rather than treating every submission as equal.
                'lead_score' => $lead->score,
                'status' => $lead->qualification,
            ], fn ($value) => $value !== null),
        ], fn ($value) => $value !== null && $value !== []);
    }

    /**
     * @return array<string, mixed>
     */
    private function userData(Lead $lead): array
    {
        $name = trim((string) $lead->name);

        return array_filter([
            'ph' => self::hash(self::phone($lead->whatsapp_normalized)),
            'fn' => self::hash(Str::before($name, ' ')),
            'ln' => self::hash(Str::contains($name, ' ') ? Str::afterLast($name, ' ') : null),
            'ct' => self::hash(preg_replace('/[^a-z]/', '', Str::lower((string) $lead->domicile))),
            'country' => self::hash('id'),
            // Not hashed, by Meta's spec.
            'fbp' => $lead->fbp,
            'fbc' => $lead->fbc,
        ], fn ($value) => filled($value));
    }

    /**
     * Meta matches on digits only, including country code and no leading
     * plus. Our stored number is already normalised to that shape.
     */
    private static function phone(?string $number): ?string
    {
        return $number === null ? null : preg_replace('/\D/', '', $number);
    }

    /**
     * Meta requires lowercase, trimmed, then SHA-256. Hashing an empty string
     * would produce a real-looking digest that matches nobody, so blanks stay
     * blank and get filtered out.
     */
    private static function hash(?string $value): ?string
    {
        $value = Str::lower(trim((string) $value));

        return $value === '' ? null : hash('sha256', $value);
    }
}
