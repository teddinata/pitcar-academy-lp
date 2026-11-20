<?php

namespace App\Filament\Analytics;

use App\Services\Analytics\MetaAds;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AnalyticsStats extends StatsOverviewWidget
{
    use ReadsReport;

    // Sums over the whole range; once a minute is fresh enough and spares the database.
    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $report = $this->report();
        $s = $report->summary();
        $meta = app(MetaAds::class)->configured();
        $noVisits = ! $report->hasVisits();

        return [
            Stat::make('Pengunjung', Format::number($s['visitors']))
                ->description($noVisits ? 'Pencatatan mulai setelah update situs tayang' : Format::number($s['views']).' halaman dibuka'),

            Stat::make('Rata-rata waktu di web', Format::duration($s['seconds_per_visitor']))
                ->description('per pengunjung, selama halaman terlihat di layar'),

            Stat::make('Penjualan', Format::number($s['sales']))
                ->description(Format::rupiah($s['revenue']).' pendapatan, DOKU + Scalev'),

            Stat::make('Konversi', Format::percent($s['conversion']))
                ->description('penjualan dibagi pengunjung'),

            Stat::make('Biaya iklan Meta', $meta ? Format::rupiah($s['spend']) : 'Belum terhubung')
                ->description($meta
                    ? ($s['cpc'] !== null ? Format::rupiah($s['cpc']).' per klik link ('.Format::number($s['link_clicks']).' klik)' : 'belum ada klik di rentang ini')
                    : 'Atur di Pengaturan › Iklan Meta'),

            Stat::make('Biaya per penjualan', Format::rupiah($s['cost_per_sale']))
                ->description($s['roas'] !== null
                    ? 'pendapatan '.number_format($s['roas'], 1, ',', '.').'x biaya iklan'
                    : 'biaya iklan dibagi semua penjualan')
                ->color($s['roas'] !== null && $s['roas'] < 1 ? 'danger' : null),
        ];
    }
}
