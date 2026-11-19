<?php

namespace Tests\Unit;

use App\Services\LeadScorer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LeadScorerTest extends TestCase
{
    private function scorer(): LeadScorer
    {
        return new LeadScorer;
    }

    public function test_it_sums_the_configured_rules(): void
    {
        $result = $this->scorer()->score([
            'readiness' => 'nearest_batch',       // 40
            'goal' => 'mechanic_career',          // 30
            'program_interest' => 'undecided',    // 12
        ]);

        $this->assertSame(82, $result['score']);
        $this->assertSame('qualified', $result['qualification']);
    }

    public function test_it_records_a_reason_for_every_awarded_rule(): void
    {
        $result = $this->scorer()->score([
            'readiness' => 'need_payment_plan',
            'goal' => 'mechanic_career',
            'program_interest' => 'basic',
        ]);

        $this->assertSame([
            ['rule' => 'readiness', 'value' => 'need_payment_plan', 'points' => 25],
            ['rule' => 'goal', 'value' => 'mechanic_career', 'points' => 30],
            ['rule' => 'program_interest', 'value' => 'basic', 'points' => 18],
        ], $result['reasons']);

        // The reasons must add up to the stored score, or a consultant cannot
        // tell where the number came from.
        $this->assertSame($result['score'], array_sum(array_column($result['reasons'], 'points')));
    }

    public function test_the_best_possible_answers_reach_one_hundred(): void
    {
        // Weights are scaled to reach exactly the cap: a field named `score`
        // gets read as a percentage, so a perfect lead has to show 100.
        $result = $this->scorer()->score([
            'readiness' => 'nearest_batch',       // 40
            'goal' => 'mechanic_career',          // 30
            'program_interest' => 'professional', // 30
        ]);

        $this->assertSame(100, $result['score']);
        $this->assertSame('hot', $result['qualification']);
    }

    public function test_it_caps_the_score(): void
    {
        config(['leads.scoring.cap' => 50]);

        $result = $this->scorer()->score([
            'readiness' => 'nearest_batch',
            'goal' => 'mechanic_career',
            'program_interest' => 'professional',
        ]);

        $this->assertSame(50, $result['score']);
    }

    public function test_it_ignores_unknown_values_instead_of_failing(): void
    {
        $result = $this->scorer()->score([
            'readiness' => 'whenever',
            'goal' => null,
            'program_interest' => 'basic',
        ]);

        $this->assertSame(18, $result['score']);
        $this->assertSame('low_intent', $result['qualification']);
    }

    public function test_it_stamps_the_scoring_version(): void
    {
        config(['leads.scoring.version' => '2027-06']);

        $this->assertSame('2027-06', $this->scorer()->score([])['version']);
    }

    /** @return array<string, array{0:int, 1:string}> */
    public static function thresholds(): array
    {
        return [
            'top' => [100, 'hot'],
            'hot boundary' => [85, 'hot'],
            'just below hot' => [84, 'qualified'],
            'qualified boundary' => [70, 'qualified'],
            'just below qualified' => [69, 'nurture'],
            'nurture boundary' => [55, 'nurture'],
            'just below nurture' => [54, 'low_intent'],
            'zero' => [0, 'low_intent'],
        ];
    }

    #[DataProvider('thresholds')]
    public function test_it_maps_scores_to_qualifications(int $score, string $expected): void
    {
        $this->assertSame($expected, $this->scorer()->qualify($score));
    }
}
