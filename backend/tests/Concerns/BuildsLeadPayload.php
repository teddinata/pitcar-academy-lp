<?php

namespace Tests\Concerns;

use Illuminate\Support\Str;

trait BuildsLeadPayload
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function leadPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'submission_id' => (string) Str::uuid(),
            'name' => 'Budi',
            'whatsapp_number' => '081234567890',
            'domicile' => 'Purwokerto',
            'activity' => 'job_seeker',
            'goal' => 'mechanic_career',
            'timeline' => 'nearest_batch',
            'investment_readiness' => 'installment',
            'program_interest' => 'basic',
            'source_cta' => 'package_basic',
            'source' => 'website',
            'consent_at' => now()->toIso8601String(),
            'attribution' => [
                'landing_page' => 'https://academy.pitcar.co.id/?utm_source=instagram',
                'referrer' => 'https://instagram.com/',
                'utm_source' => 'instagram',
                'utm_medium' => 'paid_social',
                'utm_campaign' => 'batch_october',
                'utm_content' => null,
                'utm_term' => null,
            ],
        ], $overrides);
    }
}
