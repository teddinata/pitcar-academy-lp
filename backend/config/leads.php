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
    | These are the starting values from the product brief and are NOT final —
    | they need sign-off from the sales team before production.
    */

    'scoring' => [
        'version' => env('LEAD_SCORING_VERSION', '2026-02'),
        'cap' => 100, // headroom; the current rules top out at 80

        'rules' => [
            // Three questions, three signals: what they want, why, and how
            // close they are to deciding. Current activity and a separate
            // timeline question were dropped from the form — a consultant can
            // ask those on WhatsApp without costing a conversion.
            'readiness' => [
                'nearest_batch' => 30,
                'family_discussion' => 20,
                'need_payment_plan' => 20,
                'exploring' => 10,
            ],
            'goal' => [
                'mechanic_career' => 25,
                'upskill' => 20,
                'open_workshop' => 20,
                'automotive_knowledge' => 10,
                'consultation' => 10,
            ],
            'program_interest' => [
                'professional' => 25,
                'advanced' => 20,
                'basic' => 15,
                'undecided' => 10,
            ],
        ],

        // Read as "score >= threshold" from the top down.
        'qualifications' => [
            'hot' => 70,
            'qualified' => 60,
            'nurture' => 45,
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
