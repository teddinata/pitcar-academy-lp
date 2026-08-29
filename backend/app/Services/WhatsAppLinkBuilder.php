<?php

namespace App\Services;

use App\Models\Lead;
use App\Support\LeadOptions;
use App\Support\WhatsAppNumber;

class WhatsAppLinkBuilder
{
    /**
     * Returns a wa.me deep link, or null when no consultant number is
     * configured. The frontend only accepts https wa.me / api.whatsapp.com
     * URLs, so anything else here is silently dropped by the browser.
     */
    public function build(Lead $lead): ?string
    {
        $number = $lead->consultant?->whatsapp_number
            ?? config('leads.fallback_consultant_whatsapp');

        $normalized = WhatsAppNumber::normalize($number);

        if ($normalized === null) {
            return null;
        }

        return 'https://wa.me/'.$normalized.'?text='.rawurlencode($this->message($lead));
    }

    public function message(Lead $lead): string
    {
        // Deliberately excludes score and qualification: those are internal
        // sales signals and the visitor can read this message.
        return implode("\n", [
            'Halo Education Consultant Pitcar Academy, saya sudah mengisi form konsultasi.',
            '',
            'Kode lead: '.$lead->lead_code,
            'Nama: '.$lead->name,
            'Domisili: '.$lead->domicile,
            'Aktivitas: '.LeadOptions::label(LeadOptions::ACTIVITIES, $lead->activity),
            'Tujuan: '.LeadOptions::label(LeadOptions::GOALS, $lead->goal),
            'Rencana mulai: '.LeadOptions::label(LeadOptions::TIMELINES, $lead->timeline),
            'Kesiapan investasi: '.LeadOptions::label(LeadOptions::INVESTMENT_READINESS, $lead->investment_readiness),
            'Program diminati: '.LeadOptions::label(LeadOptions::PROGRAMS, $lead->program_interest),
            '',
            'Mohon bantu rekomendasikan program dan langkah berikutnya. Terima kasih.',
        ]);
    }
}
