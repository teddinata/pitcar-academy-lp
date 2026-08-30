<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the current lead selection as CSV. Deliberately synchronous and
 * dependency-free: sales want the file now, and a queued exporter would need a
 * worker running just to hand someone a spreadsheet.
 */
class LeadCsvExporter
{
    /** @var list<string> */
    private const HEADINGS = [
        'lead_code', 'created_at', 'name', 'whatsapp', 'domicile',
        'program_interest', 'goal', 'readiness',
        'activity', 'timeline', 'investment_readiness',
        'score', 'qualification', 'status', 'consultant',
        'follow_up_due_at', 'first_contacted_at', 'converted_at', 'lost_reason',
        'source_cta', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
        'landing_page', 'referrer', 'consent_at', 'submission_count',
    ];

    public function stream(Builder $query, string $filename): StreamedResponse
    {
        // Whether an export may carry full phone numbers is a privacy decision,
        // so it is a setting rather than something baked into the code.
        $includeNumbers = (bool) config('leads.export_includes_full_number', true);

        return response()->streamDownload(function () use ($query, $includeNumbers) {
            $handle = fopen('php://output', 'wb');

            // Excel needs the BOM to read UTF-8 names correctly.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, self::HEADINGS);

            $query->with('consultant')->chunkById(500, function ($leads) use ($handle, $includeNumbers) {
                foreach ($leads as $lead) {
                    fputcsv($handle, [
                        $lead->lead_code,
                        $lead->created_at?->toDateTimeString(),
                        $lead->name,
                        $includeNumbers ? $lead->whatsapp_normalized : self::maskNumber($lead->whatsapp_normalized),
                        $lead->domicile,
                        LeadOptions::label(LeadOptions::PROGRAMS, $lead->program_interest),
                        LeadOptions::label(LeadOptions::GOALS, $lead->goal),
                        $lead->readiness ? LeadOptions::label(LeadOptions::READINESS, $lead->readiness) : '',
                        // Blank for anything captured by the short form.
                        $lead->activity ? LeadOptions::label(LeadOptions::ACTIVITIES, $lead->activity) : '',
                        $lead->timeline ? LeadOptions::label(LeadOptions::TIMELINES, $lead->timeline) : '',
                        $lead->investment_readiness ? LeadOptions::label(LeadOptions::INVESTMENT_READINESS, $lead->investment_readiness) : '',
                        $lead->score,
                        $lead->qualification,
                        $lead->status,
                        $lead->consultant?->name,
                        $lead->follow_up_due_at?->toDateTimeString(),
                        $lead->first_contacted_at?->toDateTimeString(),
                        $lead->converted_at?->toDateTimeString(),
                        $lead->lost_reason,
                        $lead->source_cta,
                        $lead->utm_source,
                        $lead->utm_medium,
                        $lead->utm_campaign,
                        $lead->utm_content,
                        $lead->utm_term,
                        $lead->landing_page,
                        $lead->referrer,
                        $lead->consent_at?->toDateTimeString(),
                        $lead->submission_count,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private static function maskNumber(?string $number): string
    {
        if (! $number) {
            return '';
        }

        return substr($number, 0, 4).str_repeat('*', max(0, strlen($number) - 8)).substr($number, -4);
    }
}
