<?php

namespace App\Observers;

use App\Models\Lead;
use App\Models\LeadStatusHistory;
use Illuminate\Support\Facades\Auth;

/**
 * Records every status change with who made it. Lives on the model rather than
 * in the dashboard so the audit trail cannot be bypassed by editing a lead from
 * somewhere else.
 */
class LeadObserver
{
    public function updating(Lead $lead): void
    {
        if (! $lead->isDirty('status')) {
            return;
        }

        $now = now();

        // Keep the milestone timestamps in step with the status.
        match ($lead->status) {
            'contacted' => $lead->forceFill([
                'first_contacted_at' => $lead->first_contacted_at ?? $now,
                'last_contacted_at' => $now,
            ]),
            'qualified' => $lead->forceFill(['qualified_at' => $now]),
            'converted' => $lead->forceFill(['converted_at' => $now]),
            'lost', 'invalid' => $lead->forceFill(['lost_at' => $now]),
            default => null,
        };
    }

    public function updated(Lead $lead): void
    {
        if (! $lead->wasChanged('status')) {
            return;
        }

        LeadStatusHistory::create([
            'lead_id' => $lead->id,
            'from_status' => $lead->getOriginal('status'),
            'to_status' => $lead->status,
            'changed_by' => Auth::user()?->name ?? 'system',
            'notes' => $lead->lost_reason,
        ]);
    }
}
