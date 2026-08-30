<?php

namespace App\Support;

/**
 * Single source of truth for the enum values the landing page sends.
 *
 * These mirror docs/lead-api-contract.md in the frontend repo. Changing a key
 * here is a breaking API change and needs a matching change in the Astro form.
 */
class LeadOptions
{
    public const ACTIVITIES = [
        'student' => 'Pelajar / baru lulus',
        'job_seeker' => 'Sedang mencari kerja',
        'mechanic' => 'Mekanik',
        'employee' => 'Karyawan bidang lain',
        'workshop_owner' => 'Pemilik / calon pemilik bengkel',
        'other' => 'Lainnya',
    ];

    public const GOALS = [
        'mechanic_career' => 'Berkarier sebagai mekanik',
        'upskill' => 'Meningkatkan skill mekanik',
        'open_workshop' => 'Membuka / mengembangkan bengkel',
        'automotive_knowledge' => 'Belajar otomotif lebih serius',
        // Retained so leads captured before the short form still label correctly.
        'consultation' => 'Masih ingin konsultasi',
    ];

    /**
     * One "how close are you to deciding" question, replacing the separate
     * timeline and investment questions. Worded around readiness rather than
     * budget: asking a school leaver about their finances on a landing page
     * loses leads before a conversation can start.
     */
    public const READINESS = [
        'nearest_batch' => 'Siap ikut batch terdekat',
        'family_discussion' => 'Perlu diskusi dengan orang tua / keluarga',
        'need_payment_plan' => 'Perlu opsi pembayaran / cicilan',
        'exploring' => 'Masih ingin memahami program',
    ];

    public const TIMELINES = [
        'nearest_batch' => 'Batch terdekat',
        'one_to_three_months' => '1-3 bulan',
        'three_to_six_months' => '3-6 bulan',
        'considering' => 'Masih mempertimbangkan',
    ];

    public const INVESTMENT_READINESS = [
        'ready' => 'Siap untuk program mulai Rp5 juta',
        'installment' => 'Memerlukan opsi cicilan',
        'family_discussion' => 'Perlu diskusi dengan orang tua / keluarga',
        'researching' => 'Masih mencari informasi',
    ];

    public const PROGRAMS = [
        'basic' => 'Basic - Maintenance',
        'advanced' => 'Advance - General Repair',
        'professional' => 'Professional - Level 1 & 2',
        'undecided' => 'Belum yakin, perlu rekomendasi',
    ];

    public const STATUSES = [
        'new', 'assigned', 'contacted', 'consultation', 'qualified',
        'nurture', 'registered', 'converted', 'lost', 'invalid',
    ];

    public static function label(array $map, string $value): string
    {
        return $map[$value] ?? $value;
    }
}
