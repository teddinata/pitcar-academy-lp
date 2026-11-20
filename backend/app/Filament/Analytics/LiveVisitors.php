<?php

namespace App\Filament\Analytics;

use App\Models\PageVisit;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Who has a page open right now, and today's total. Not tied to the range filter. */
class LiveVisitors extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected ?string $pollingInterval = '10s';

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 2;
    }

    protected function getStats(): array
    {
        $live = PageVisit::query()
            ->where('last_seen_at', '>=', now()->subSeconds(PageVisit::LIVE_WINDOW_SECONDS))
            ->get(['visitor_id', 'path']);
        $now = $live->unique('visitor_id')->count();
        // Someone with two tabs open counts once per page they have open.
        $pages = $live->unique(fn ($v) => $v->visitor_id.$v->path)
            ->countBy('path')
            ->sortDesc()
            ->take(3)
            ->map(fn (int $n, string $path) => $n.' di '.Format::pageName($path))
            ->implode(', ');

        $today = PageVisit::query()->where('visit_date', now(config('app.display_timezone'))->toDateString());

        return [
            Stat::make('Sedang di web', Format::number($now))
                ->description($now ? $pages : 'Tidak ada yang membuka situs dalam 2 menit terakhir')
                ->color($now ? 'success' : null),

            Stat::make('Pengunjung hari ini', Format::number((clone $today)->distinct()->count('visitor_id')))
                ->description('sejak 00.00 WIB, '.Format::number((clone $today)->count()).' halaman dibuka'),
        ];
    }
}
