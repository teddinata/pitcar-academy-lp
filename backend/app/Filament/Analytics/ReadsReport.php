<?php

namespace App\Filament\Analytics;

use App\Services\Analytics\OnlineReport;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/** Every widget on the analytics page reads the same range from the page filter. */
trait ReadsReport
{
    use InteractsWithPageFilters;

    private ?OnlineReport $report = null;

    protected function report(): OnlineReport
    {
        $range = $this->pageFilters['range'] ?? '30';

        if ($this->report === null || $this->reportRange !== $range) {
            $this->report = OnlineReport::forPreset($range);
            $this->reportRange = $range;
        }

        return $this->report;
    }

    private ?string $reportRange = null;
}
