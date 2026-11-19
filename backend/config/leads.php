<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lead code
    |--------------------------------------------------------------------------
    | Codes look like PA-2026-000123. The counter lives in `lead_sequences`
    | and is taken under a row lock, so concurrent submissions cannot collide.
    */

    'code_prefix' => env('LEAD_CODE_PREFIX', 'PA'),
    'code_padding' => 6,

    /*
    |--------------------------------------------------------------------------
    | Scoring
    |--------------------------------------------------------------------------
    | Rules are config so sales can retune them without a deploy of new code.
    | Bump `version` whenever the numbers change: it is stored on every lead so
    | historic scores stay explainable and rescoring stays auditable.
    |
    | Weights are scaled so the best possible answers total exactly 100. A
    | field called `score` is read as a percentage whether or not it is one,
    | and a ceiling of 80 makes a perfect lead look like it fell short. The
    | points still sum to the stored score, so `scoring_reasons` stays additive
    | and a consultant can see where the number came from.
    |
    | These are the starting values from the product brief and are NOT final —
    | they need sign-off from the sales team before production.
    */

    'scoring' => [
        'version' => env('LEAD_SCORING_VERSION', '2026-03'),
        'cap' => 100, // the rules are weighted to reach exactly this

        'rules' => [
            // Three questions, three signals: what they want, why, and how
            // close they are to deciding. Current activity and a separate
            // timeline question were dropped from the form — a consultant can
            // ask those on WhatsApp without costing a conversion.
            'readiness' => [
                'nearest_batch' => 40,
                'family_discussion' => 25,
                'need_payment_plan' => 25,
                'exploring' => 12,
            ],
            'goal' => [
                'mechanic_career' => 30,
                'upskill' => 25,
                'open_workshop' => 25,
                'automotive_knowledge' => 12,
                'consultation' => 12,
            ],
            'program_interest' => [
                'professional' => 30,
                'advanced' => 25,
                'basic' => 18,
                'undecided' => 12,
            ],
        ],

        // Read as "score >= threshold" from the top down.
        // Three questions yield only 23 distinct scores, so the bands cannot
        // land on round numbers without distorting the split. These give
        // hot 14% / qualified 33% / nurture 39% / low 14%.
        'qualifications' => [
            'hot' => 85,
            'qualified' => 70,
            'nurture' => 55,
            'low_intent' => 0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Education Consultant routing
    |--------------------------------------------------------------------------
    | Consultants are matched from the `education_consultants` table. When none
    | is eligible the lead is still stored and handed to the fallback number so
    | the visitor never hits a dead end.
    */

    'fallback_consultant_whatsapp' => env('LEAD_FALLBACK_CONSULTANT_WHATSAPP'),

    /*
    |--------------------------------------------------------------------------
    | Integrasi keluar
    |--------------------------------------------------------------------------
    | Webhook Cekat AI yang menjalankan otomasi WhatsApp. Dipanggil dari queue
    | setelah lead tersimpan, bukan dari browser: lead yang sudah masuk
    | database tidak boleh hilang hanya karena layanan pihak ketiga sedang
    | bermasalah, dan URL-nya tidak perlu terlihat di source halaman.
    */

    'webhook_url' => env('LEAD_WEBHOOK_URL'),
    'webhook_timeout' => (int) env('LEAD_WEBHOOK_TIMEOUT', 8),

    /*
    |--------------------------------------------------------------------------
    | Abuse protection
    |--------------------------------------------------------------------------
    | Tune against real traffic after launch. Too tight costs real leads.
    */

    'rate_limit' => [
        'per_ip' => (int) env('LEAD_RATE_LIMIT_PER_IP', 10),          // per minute
        'per_whatsapp' => (int) env('LEAD_RATE_LIMIT_PER_WHATSAPP', 3), // per hour
    ],

    'max_payload_bytes' => (int) env('LEAD_MAX_PAYLOAD_BYTES', 16384),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    | Days after which `leads:apply-retention-policy` anonymises PII. Null keeps
    | data indefinitely, which is almost certainly not what the privacy policy
    | should say — set this once legal has decided.
    */

    // Whether a CSV export carries full WhatsApp numbers or masked ones.
    // Confirm against the privacy policy before enabling in production.
    'export_includes_full_number' => (bool) env('LEAD_EXPORT_FULL_NUMBER', true),

    'retention_days' => env('LEAD_RETENTION_DAYS') ? (int) env('LEAD_RETENTION_DAYS') : null,

];
