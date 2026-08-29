<?php

namespace Tests\Feature\Dashboard;

use App\Models\Lead;
use App\Support\LeadCsvExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsLeadPayload;
use Tests\TestCase;

class LeadExportTest extends TestCase
{
    use BuildsLeadPayload;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function csv(Builder $query): string
    {
        $response = app(LeadCsvExporter::class)->stream($query, 'test.csv');

        ob_start();
        $response->sendContent();

        return ob_get_clean();
    }

    public function test_it_exports_the_lead_with_readable_labels(): void
    {
        $this->postJson('/api/leads', $this->leadPayload());
        $lead = Lead::sole();

        $csv = $this->csv(Lead::query());

        $this->assertStringContainsString($lead->lead_code, $csv);
        $this->assertStringContainsString('Budi', $csv);
        $this->assertStringContainsString('Purwokerto', $csv);
        // Enum keys are useless to sales; the export carries the labels.
        $this->assertStringContainsString('Sedang mencari kerja', $csv);
        $this->assertStringContainsString('Batch terdekat', $csv);
        $this->assertStringContainsString('instagram', $csv);
    }

    public function test_it_starts_with_a_bom_so_excel_reads_utf8(): void
    {
        $this->postJson('/api/leads', $this->leadPayload(['name' => 'Ayu Wulandari']));

        $csv = $this->csv(Lead::query());

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Ayu Wulandari', $csv);
    }

    public function test_it_exports_only_the_rows_the_query_selects(): void
    {
        $this->postJson('/api/leads', $this->leadPayload(['whatsapp_number' => '081200000001', 'name' => 'Lead Satu']));
        $this->postJson('/api/leads', $this->leadPayload(['whatsapp_number' => '081200000002', 'name' => 'Lead Dua']));

        $csv = $this->csv(Lead::query()->where('name', 'Lead Satu'));

        $this->assertStringContainsString('Lead Satu', $csv);
        $this->assertStringNotContainsString('Lead Dua', $csv);
    }

    public function test_it_can_mask_phone_numbers_when_privacy_requires_it(): void
    {
        config(['leads.export_includes_full_number' => false]);
        $this->postJson('/api/leads', $this->leadPayload());

        $csv = $this->csv(Lead::query());

        $this->assertStringNotContainsString('6281234567890', $csv);
        $this->assertStringContainsString('6281*****7890', $csv);
    }

    public function test_it_includes_full_numbers_by_default(): void
    {
        $this->postJson('/api/leads', $this->leadPayload());

        $this->assertStringContainsString('6281234567890', $this->csv(Lead::query()));
    }

    public function test_the_header_row_names_every_column(): void
    {
        $this->postJson('/api/leads', $this->leadPayload());

        $header = strtok($this->csv(Lead::query()), "\n");

        foreach (['lead_code', 'qualification', 'status', 'utm_campaign', 'consent_at'] as $column) {
            $this->assertStringContainsString($column, $header);
        }
    }
}
