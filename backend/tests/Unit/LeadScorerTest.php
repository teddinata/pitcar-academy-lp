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
            'readiness' => 'nearest_batch',       // 30
            'goal' => 'mechanic_career',          // 25
            'program_interest' => 'undecided',    // 10
        ]);

        $this->assertSame(65, $result['score']);
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
            ['rule' => 'readiness', 'value' => 'need_payment_plan', 'points' => 20],
            ['rule' => 'goal', 'value' => 'mechanic_career', 'points' => 25],
            ['rule' => 'program_interest', 'value' => 'basic', 'points' => 15],
        ], $result['reasons']);

        $this->assertSame(60, array_sum(array_column($result['reasons'], 'points')));
    }

    public function test_the_best_possible_answers_reach_hot(): void
    {
        // The rules top out at 80, so `hot` must sit below that or nothing
        // a visitor can actually answer would ever qualify.
        $result = $this->scorer()->score([
            'readiness' => 'nearest_batch',       // 30
            'goal' => 'mechanic_career',          // 25
            'program_interest' => 'professional', // 25
        ]);

        $this->assertSame(80, $result['score']);
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

        $this->assertSame(15, $result['score']);
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
            'top' => [80, 'hot'],
            'hot boundary' => [70, 'hot'],
            'just below hot' => [69, 'qualified'],
            'qualified boundary' => [60, 'qualified'],
            'just below qualified' => [59, 'nurture'],
            'nurture boundary' => [45, 'nurture'],
            'just below nurture' => [44, 'low_intent'],
            'zero' => [0, 'low_intent'],
        ];
    }

    #[DataProvider('thresholds')]
    public function test_it_maps_scores_to_qualifications(int $score, string $expected): void
    {
        $this->assertSame($expected, $this->scorer()->qualify($score));
    }
}
