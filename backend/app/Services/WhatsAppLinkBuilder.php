<?php

namespace App\Services;

use App\Models\Lead;
use App\Support\LeadOptions;
use App\Support\WhatsAppNumber;
use Illuminate\Support\Str;

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

    /**
     * The other direction: a consultant on the dashboard opening a chat with
     * the lead. Same class, opposite ends — the recipient is the visitor's
     * own number and the message is written by the consultant.
     */
    public function buildFollowUp(Lead $lead): ?string
    {
        $normalized = WhatsAppNumber::normalize($lead->whatsapp_normalized ?? $lead->whatsapp_number);

        if ($normalized === null) {
            return null;
        }

        return 'https://wa.me/'.$normalized.'?text='.rawurlencode($this->followUpMessage($lead));
    }

    public function followUpMessage(Lead $lead): string
    {
        $sender = $lead->consultant?->name
            ? 'saya '.Str::before($lead->consultant->name, ' ').' dari Pitcar Academy'
            : 'saya dari Pitcar Academy';

        // No array_filter here: the empty strings are the paragraph breaks
        // WhatsApp renders, and filtering collapses the message into a wall.
        return implode("\n", [
            'Halo '.Str::before($lead->name, ' ').', '.$sender.'.',
            '',
            'Terima kasih sudah mengisi form konsultasi. Saya lihat Kakak tertarik dengan program '
                .LeadOptions::label(LeadOptions::PROGRAMS, $lead->program_interest).'.',
            '',
            $this->openingQuestion($lead),
        ]);
    }

    /**
     * Opens on what the visitor already told us they need. A consultant can
     * still rewrite it before sending; this only removes the blank-page
     * moment that makes follow-ups get postponed.
     */
    private function openingQuestion(Lead $lead): string
    {
        return match ($lead->readiness) {
            'nearest_batch' => 'Batch terdekat masih ada tempat. Boleh saya kirimkan jadwal dan langkah pendaftarannya?',
            'family_discussion' => 'Kalau perlu didiskusikan dulu dengan orang tua, saya bisa kirimkan ringkasan program dan rincian biayanya supaya lebih mudah dibahas bersama.',
            'need_payment_plan' => 'Untuk biayanya ada beberapa opsi pembayaran. Boleh saya jelaskan yang paling sesuai?',
            'exploring' => 'Boleh saya jelaskan dulu isi programnya dan bedanya dengan kelas lain, supaya Kakak bisa menimbang dengan tenang?',
            default => 'Boleh saya bantu jelaskan jadwal dan rincian programnya?',
        };
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
            'Program diminati: '.LeadOptions::label(LeadOptions::PROGRAMS, $lead->program_interest),
            'Tujuan: '.LeadOptions::label(LeadOptions::GOALS, $lead->goal),
            'Kesiapan: '.LeadOptions::label(LeadOptions::READINESS, $lead->readiness),
            '',
            'Mohon bantu rekomendasikan program dan langkah berikutnya. Terima kasih.',
        ]);
    }
}
