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
            'timeline' => 'nearest_batch',        // 25
            'investment_readiness' => 'ready',    // 25
            'goal' => 'mechanic_career',          // 20
            'activity' => 'student',              // 8
            'program_interest' => 'undecided',    // 0
        ]);

        $this->assertSame(78, $result['score']);
        $this->assertSame('qualified', $result['qualification']);
    }

    public function test_it_records_a_reason_for_every_awarded_rule(): void
    {
        $result = $this->scorer()->score([
            'timeline' => 'nearest_batch',
            'investment_readiness' => 'installment',
            'goal' => 'mechanic_career',
            'activity' => 'other',
            'program_interest' => 'undecided',
        ]);

        // program_interest scores zero and is left out rather than logged as noise.
        $this->assertSame([
            ['rule' => 'timeline', 'value' => 'nearest_batch', 'points' => 25],
            ['rule' => 'investment_readiness', 'value' => 'installment', 'points' => 15],
            ['rule' => 'goal', 'value' => 'mechanic_career', 'points' => 20],
            ['rule' => 'activity', 'value' => 'other', 'points' => 5],
        ], $result['reasons']);

        $this->assertSame(65, array_sum(array_column($result['reasons'], 'points')));
    }

    public function test_it_caps_the_score(): void
    {
        $result = $this->scorer()->score([
            'timeline' => 'nearest_batch',        // 25
            'investment_readiness' => 'ready',    // 25
            'goal' => 'open_workshop',            // 20
            'activity' => 'workshop_owner',       // 20
            'program_interest' => 'professional', // 10
        ]);

        $this->assertSame(100, $result['score']);
        $this->assertSame('hot', $result['qualification']);
    }

    public function test_it_ignores_unknown_values_instead_of_failing(): void
    {
        $result = $this->scorer()->score([
            'timeline' => 'whenever',
            'investment_readiness' => 'ready',
            'goal' => null,
            'activity' => ['not', 'a', 'string'],
            'program_interest' => 'basic',
        ]);

        $this->assertSame(35, $result['score']);
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
            'hot boundary' => [80, 'hot'],
            'just below hot' => [79, 'qualified'],
            'qualified boundary' => [60, 'qualified'],
            'just below qualified' => [59, 'nurture'],
            'nurture boundary' => [40, 'nurture'],
            'just below nurture' => [39, 'low_intent'],
            'zero' => [0, 'low_intent'],
        ];
    }

    #[DataProvider('thresholds')]
    public function test_it_maps_scores_to_qualifications(int $score, string $expected): void
    {
        $this->assertSame($expected, $this->scorer()->qualify($score));
    }
}
