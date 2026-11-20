<?php

namespace App\Console\Commands;

use App\Services\Analytics\MetaAds;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use RuntimeException;

class SyncMetaAds extends Command
{
    protected $signature = 'meta:sync-ads {--days=3 : How many days back to pull, today included}';

    protected $description = 'Pull Meta Ads spend, link clicks and purchases per campaign per day';

    public function handle(MetaAds $meta): int
    {
        if (! $meta->configured()) {
            $this->info('Akun iklan Meta belum diatur di admin. Dilewati.');

            return self::SUCCESS;
        }

        $days = max(1, min(400, (int) $this->option('days')));
        // Meta reports days in the ad account's timezone; WIB is the account's for Pitcar.
        $today = CarbonImmutable::now(config('app.display_timezone'));

        try {
            $rows = $meta->sync($today->subDays($days - 1)->startOfDay(), $today->startOfDay());
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Tersimpan {$rows} baris kampanye per hari.");

        return self::SUCCESS;
    }
}
