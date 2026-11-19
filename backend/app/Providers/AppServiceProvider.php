<?php

namespace App\Providers;

use App\Models\Lead;
use App\Support\WhatsAppNumber;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Stored UTC, read in WIB. Without this the panel shows sales a time
        // seven hours behind the one on their own clock, which makes every
        // follow-up SLA look wrong.
        FilamentTimezone::set(config('app.display_timezone'));

        $this->configureLeadRateLimiting();
    }

    /**
     * Two limits guard the intake endpoint: a per-IP burst limit, and a slower
     * per-number limit so one phone number cannot flood the pipeline. Both are
     * starting values — retune them against real traffic, because a limit that
     * is too tight silently costs real leads.
     */
    private function configureLeadRateLimiting(): void
    {
        RateLimiter::for('leads', function (Request $request) {
            $limits = [
                Limit::perMinute((int) config('leads.rate_limit.per_ip'))
                    ->by('ip:'.$request->ip())
                    ->response(fn (Request $request, array $headers) => $this->throttled($headers)),
            ];

            // Runs before validation, so treat the number as untrusted input.
            $number = WhatsAppNumber::normalize($request->input('whatsapp_number'));

            // The per-number budget counts leads, not requests: an idempotent
            // retry of a submission we already stored must not lock a genuine
            // visitor out of finishing their consultation.
            if ($number !== null && ! $this->isReplay($request)) {
                $limits[] = Limit::perHour((int) config('leads.rate_limit.per_whatsapp'))
                    ->by('wa:'.$number)
                    ->response(fn (Request $request, array $headers) => $this->throttled($headers));
            }

            return $limits;
        });
    }

    private function isReplay(Request $request): bool
    {
        $submissionId = $request->input('submission_id');

        return is_string($submissionId)
            && $submissionId !== ''
            && Lead::query()->where('submission_id', $submissionId)->exists();
    }

    /**
     * @param  array<string, mixed>  $headers  Retry-After and X-RateLimit-* from the limiter.
     */
    private function throttled(array $headers = []): JsonResponse
    {
        return response()->json(
            ['message' => 'Terlalu banyak percobaan. Silakan coba kembali beberapa saat lagi.'],
            429,
            $headers
        );
    }
}
