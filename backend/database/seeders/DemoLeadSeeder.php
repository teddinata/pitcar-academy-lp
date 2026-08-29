<?php

namespace Database\Seeders;

use App\Models\EducationConsultant;
use App\Services\LeadIntake;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Sample data for local work, so the dashboard has something to filter and the
 * scoring bands are visible. Goes through the real intake service, so codes,
 * scores and routing are the same ones production would produce.
 */
class DemoLeadSeeder extends Seeder
{
    public function run(LeadIntake $intake): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoLeadSeeder is for local use only.');

            return;
        }

        if (EducationConsultant::query()->count() === 0) {
            EducationConsultant::query()->create([
                'name' => 'Rina Kartika',
                'whatsapp_number' => '628111222333',
                'is_active' => true,
                'max_active_leads' => 100,
                'priority' => 10,
            ]);
            EducationConsultant::query()->create([
                'name' => 'Dimas Prakoso',
                'whatsapp_number' => '628111222444',
                'is_active' => true,
                'programs' => ['professional'],
                'max_active_leads' => 50,
                'priority' => 5,
            ]);
        }

        $samples = [
            ['Budi Santoso', 'Purwokerto', 'job_seeker', 'mechanic_career', 'nearest_batch', 'ready', 'basic', 'package_basic', 'instagram'],
            ['Sari Rahmawati', 'Yogyakarta', 'student', 'automotive_knowledge', 'considering', 'researching', 'undecided', 'hero_primary', null],
            ['Agus Setiawan', 'Solo', 'mechanic', 'upskill', 'one_to_three_months', 'installment', 'advanced', 'package_advanced', 'facebook'],
            ['Dewi Lestari', 'Semarang', 'workshop_owner', 'open_workshop', 'nearest_batch', 'ready', 'professional', 'package_professional', 'google'],
            ['Rizky Ramadhan', 'Purbalingga', 'employee', 'mechanic_career', 'three_to_six_months', 'family_discussion', 'basic', 'faq_consultation', 'instagram'],
            ['Nur Aisyah', 'Cilacap', 'student', 'consultation', 'considering', 'researching', 'undecided', 'footer_consultation', null],
            ['Joko Widodo', 'Banyumas', 'mechanic', 'open_workshop', 'nearest_batch', 'installment', 'professional', 'audience_consultation', 'tiktok'],
            ['Maya Putri', 'Magelang', 'job_seeker', 'mechanic_career', 'one_to_three_months', 'ready', 'advanced', 'header_desktop', 'instagram'],
        ];

        foreach ($samples as $i => [$name, $city, $activity, $goal, $timeline, $money, $program, $cta, $utm]) {
            $intake->handle([
                'submission_id' => 'demo-'.Str::uuid(),
                'name' => $name,
                'whatsapp_number' => '0812'.str_pad((string) (10000000 + $i), 8, '0', STR_PAD_LEFT),
                'domicile' => $city,
                'activity' => $activity,
                'goal' => $goal,
                'timeline' => $timeline,
                'investment_readiness' => $money,
                'program_interest' => $program,
                'source_cta' => $cta,
                'source' => 'website',
                'consent_at' => now()->subDays(random_int(0, 12))->toIso8601String(),
                'attribution' => [
                    'landing_page' => 'https://academy.pitcar.co.id/',
                    'referrer' => $utm ? "https://{$utm}.com/" : null,
                    'utm_source' => $utm,
                    'utm_medium' => $utm ? 'paid_social' : null,
                    'utm_campaign' => $utm ? 'batch_oktober' : null,
                    'utm_content' => null,
                    'utm_term' => null,
                ],
            ]);
        }

        // Spread them over the last two weeks so the chart is not one spike.
        \App\Models\Lead::query()->where('submission_id', 'like', 'demo-%')->get()
            ->each(fn ($lead, $i) => $lead->forceFill([
                'created_at' => now()->subDays(13 - ($i % 13))->subHours(random_int(1, 20)),
            ])->save());

        $this->command?->info('Seeded '.count($samples).' demo leads and 2 consultants.');
    }
}
