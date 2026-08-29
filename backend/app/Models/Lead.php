<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Only values a visitor is allowed to supply. Score, qualification, status,
     * lead_code and consultant assignment are set explicitly by the intake
     * service so a crafted request cannot promote its own lead.
     */
    protected $fillable = [
        'submission_id',
        'name',
        'whatsapp_number',
        'whatsapp_normalized',
        'domicile',
        'activity',
        'goal',
        'timeline',
        'investment_readiness',
        'program_interest',
        'source',
        'source_cta',
        'consent_at',
        'landing_page',
        'referrer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
    ];

    protected function casts(): array
    {
        return [
            'consent_at' => 'datetime',
            'scored_at' => 'datetime',
            'assigned_at' => 'datetime',
            'follow_up_due_at' => 'datetime',
            'first_contacted_at' => 'datetime',
            'last_contacted_at' => 'datetime',
            'qualified_at' => 'datetime',
            'converted_at' => 'datetime',
            'lost_at' => 'datetime',
            'last_submitted_at' => 'datetime',
            'scoring_reasons' => 'array',
            'score' => 'integer',
            'submission_count' => 'integer',
        ];
    }

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(EducationConsultant::class, 'assigned_consultant_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(LeadStatusHistory::class);
    }
}
