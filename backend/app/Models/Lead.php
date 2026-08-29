<?php

namespace App\Models;

use App\Observers\LeadObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;

#[ObservedBy(LeadObserver::class)]
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

    /**
     * Pipeline fields stay out of $fillable so the public intake endpoint can
     * never set them. The dashboard is authenticated and authorised, so it
     * writes them through this explicit, allow-listed path instead.
     *
     * @param  array<string, mixed>  $data
     */
    public function updatePipeline(array $data): bool
    {
        $allowed = Arr::only($data, [
            'status',
            'assigned_consultant_id',
            'assignment_reason',
            'assigned_at',
            'follow_up_due_at',
            'lost_reason',
        ]);

        if (array_key_exists('assigned_consultant_id', $allowed)
            && $allowed['assigned_consultant_id'] !== $this->assigned_consultant_id) {
            $allowed['assignment_reason'] = 'manual_assignment';
            $allowed['assigned_at'] = $allowed['assigned_consultant_id'] ? now() : null;
        }

        // A reason only makes sense while the lead is actually lost.
        if (array_key_exists('status', $allowed) && ! in_array($allowed['status'], ['lost', 'invalid'], true)) {
            $allowed['lost_reason'] = null;
        }

        return $this->forceFill($allowed)->save();
    }

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(EducationConsultant::class, 'assigned_consultant_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(LeadStatusHistory::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class);
    }
}
