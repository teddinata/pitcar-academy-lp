<?php

namespace App\Filament\Analytics;

use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class VisitorsSalesChart extends ChartWidget
{
    use ReadsReport;

    // Sums over the whole range; once a minute is fresh enough and spares the database.
    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Pengunjung dan penjualan per hari';

    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $days = $this->report()->daily();

        return [
            'datasets' => [
                [
                    'type' => 'line',
                    'label' => 'Pengunjung',
                    'data' => array_column($days, 'visitors'),
                    'borderColor' => '#78716C',
                    'backgroundColor' => '#78716C',
                    'pointRadius' => 2,
                    'tension' => 0.25,
                    'yAxisID' => 'visitors',
                    'order' => 1,
                ],
                [
                    'type' => 'bar',
                    'label' => 'Penjualan',
                    'data' => array_column($days, 'sales'),
                    'backgroundColor' => '#CC0000',
                    'borderRadius' => 2,
                    'yAxisID' => 'sales',
                    'order' => 2,
                ],
            ],
            'labels' => array_map(fn ($d) => CarbonImmutable::parse($d)->locale('id')->translatedFormat('d M'), array_keys($days)),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            // Indonesian number format on axes and tooltips: 250.000, not 250,000.
            'locale' => 'id-ID',
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'scales' => [
                // Chart.js keeps its default axis next to the named ones unless told not to.
                'y' => ['display' => false],
                'sales' => ['position' => 'left', 'beginAtZero' => true, 'ticks' => ['precision' => 0], 'title' => ['display' => true, 'text' => 'Penjualan']],
                'visitors' => ['position' => 'right', 'beginAtZero' => true, 'ticks' => ['precision' => 0], 'grid' => ['drawOnChartArea' => false], 'title' => ['display' => true, 'text' => 'Pengunjung']],
            ],
        ];
    }
}
