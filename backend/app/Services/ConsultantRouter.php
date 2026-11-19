<?php

namespace App\Services;

use App\Models\EducationConsultant;
use Illuminate\Support\Str;

/**
 * Picks who receives the lead. Routing lives on the server so the browser can
 * never choose which number it is handed.
 */
class ConsultantRouter
{
    /** Statuses that still occupy a consultant's capacity. */
    private const ACTIVE_STATUSES = ['new', 'assigned', 'contacted', 'consultation', 'qualified', 'nurture'];

    /**
     * @return array{consultant: ?EducationConsultant, reason: string}
     */
    public function route(string $programInterest, string $domicile): array
    {
        $candidates = EducationConsultant::query()
            ->where('is_active', true)
            ->withCount(['leads as active_leads_count' => fn ($query) => $query->whereIn('status', self::ACTIVE_STATUSES)])
            ->orderBy('priority')
            ->orderBy('active_leads_count')
            ->orderBy('id')
            ->get();

        if ($candidates->isEmpty()) {
            return ['consultant' => null, 'reason' => 'no_consultant_configured'];
        }

        $available = $candidates->filter(
            fn (EducationConsultant $consultant) => $consultant->active_leads_count < $consultant->max_active_leads
        );

        if ($available->isEmpty()) {
            // Never drop the lead over capacity — the least loaded person still
            // gets it and the reason is recorded for the SLA report.
            return ['consultant' => $candidates->first(), 'reason' => 'all_at_capacity'];
        }

        $byProgramAndDomicile = $available->first(
            fn (EducationConsultant $consultant) => $this->matchesProgram($consultant, $programInterest)
                && $this->matchesDomicile($consultant, $domicile)
        );

        if ($byProgramAndDomicile) {
            return ['consultant' => $byProgramAndDomicile, 'reason' => 'program_and_domicile_match'];
        }

        $byProgram = $available->first(fn (EducationConsultant $c) => $this->matchesProgram($c, $programInterest));

        if ($byProgram) {
            return ['consultant' => $byProgram, 'reason' => 'program_match'];
        }

        $generalist = $available->first(fn (EducationConsultant $c) => empty($c->programs) && empty($c->domiciles));

        if ($generalist) {
            return ['consultant' => $generalist, 'reason' => 'generalist'];
        }

        return ['consultant' => $available->first(), 'reason' => 'least_loaded'];
    }

    private function matchesProgram(EducationConsultant $consultant, string $programInterest): bool
    {
        return empty($consultant->programs) || in_array($programInterest, $consultant->programs, true);
    }

    private function matchesDomicile(EducationConsultant $consultant, string $domicile): bool
    {
        if (empty($consultant->domiciles)) {
            return true;
        }

        foreach ($consultant->domiciles as $candidate) {
            if (Str::contains(Str::lower($domicile), Str::lower((string) $candidate))) {
                return true;
            }
        }

        return false;
    }
}
