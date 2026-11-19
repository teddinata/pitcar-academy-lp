<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Scoring runs here and nowhere else — a `score` sent by the browser is
 * ignored. Every awarded point is recorded with the rule that produced it so
 * sales can see why a lead is hot and so leads stay explainable after retuning.
 */
class LeadScorer
{
    /**
     * @param  array<string, mixed>  $answers
     * @return array{score:int, qualification:string, reasons:array<int, array{rule:string, value:string, points:int}>, version:string, scored_at:Carbon}
     */
    public function score(array $answers): array
    {
        $config = config('leads.scoring');
        $reasons = [];
        $total = 0;

        foreach ($config['rules'] as $field => $points) {
            $value = $answers[$field] ?? null;

            if (! is_string($value) || ! array_key_exists($value, $points)) {
                continue;
            }

            $awarded = (int) $points[$value];

            if ($awarded === 0) {
                continue;
            }

            $total += $awarded;
            $reasons[] = ['rule' => $field, 'value' => $value, 'points' => $awarded];
        }

        $cap = (int) $config['cap'];
        $score = min($total, $cap);

        return [
            'score' => $score,
            'qualification' => $this->qualify($score),
            'reasons' => $reasons,
            'version' => (string) $config['version'],
            'scored_at' => now(),
        ];
    }

    public function qualify(int $score): string
    {
        $thresholds = config('leads.scoring.qualifications');
        arsort($thresholds);

        foreach ($thresholds as $qualification => $minimum) {
            if ($score >= $minimum) {
                return (string) $qualification;
            }
        }

        return 'low_intent';
    }
}
