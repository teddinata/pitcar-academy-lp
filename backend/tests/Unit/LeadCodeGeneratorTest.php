<?php

namespace Tests\Unit;

use App\Services\LeadCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeadCodeGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_issues_sequential_codes_per_year(): void
    {
        $generator = new LeadCodeGenerator;

        DB::transaction(function () use ($generator) {
            $this->assertSame('PA-2026-000001', $generator->next(2026));
            $this->assertSame('PA-2026-000002', $generator->next(2026));
            $this->assertSame('PA-2027-000001', $generator->next(2027));
            $this->assertSame('PA-2026-000003', $generator->next(2026));
        });
    }

    public function test_it_honours_the_configured_prefix_and_padding(): void
    {
        config(['leads.code_prefix' => 'PAX', 'leads.code_padding' => 4]);

        DB::transaction(function () {
            $this->assertSame('PAX-2026-0001', (new LeadCodeGenerator)->next(2026));
        });
    }
}
