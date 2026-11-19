<?php

namespace App\Console\Commands;

use App\Models\Lead;
use Illuminate\Console\Command;

/**
 * Anonymises PII on leads older than the retention window while keeping the
 * non-identifying columns funnel reporting depends on (score, qualification,
 * program, UTM). Schedule it daily once legal has set LEAD_RETENTION_DAYS.
 */
class ApplyLeadRetentionPolicy extends Command
{
    protected $signature = 'leads:apply-retention-policy {--dry-run : Report what would change without writing}';

    protected $description = 'Anonymise personal data on leads past the retention window';

    public function handle(): int
    {
        $days = config('leads.retention_days');

        if (! is_int($days) || $days <= 0) {
            $this->warn('LEAD_RETENTION_DAYS is not set — nothing to do. Set it once the privacy policy is agreed.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);

        $query = Lead::query()
            ->where('created_at', '<', $cutoff)
            ->where('name', '!=', '[anonymised]');

        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("{$count} lead(s) created before {$cutoff->toDateString()} would be anonymised.");

            return self::SUCCESS;
        }

        $query->chunkById(500, function ($leads) {
            foreach ($leads as $lead) {
                $lead->forceFill([
                    'name' => '[anonymised]',
                    'whatsapp_number' => '',
                    // Keep a stable, non-reversible marker so historic rows stay
                    // distinct without holding a real number.
                    'whatsapp_normalized' => 'anon-'.substr(hash('sha256', (string) $lead->id.config('app.key')), 0, 12),
                    'domicile' => '[anonymised]',
                    'landing_page' => null,
                    'referrer' => null,
                ])->save();
            }
        });

        $this->info("Anonymised {$count} lead(s) created before {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
