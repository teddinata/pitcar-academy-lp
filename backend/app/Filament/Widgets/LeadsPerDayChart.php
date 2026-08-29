<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Leads\LeadResource;
use Filament\Widgets\ChartWidget;

class LeadsPerDayChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Lead 14 hari terakhir';

    protected function getData(): array
    {
        $days = collect(range(13, 0))->map(fn (int $ago) => now()->subDays($ago)->startOfDay());

        // One grouped query rather than 14 counts.
        $counts = LeadResource::getEloquentQuery()
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->get(['created_at'])
            ->countBy(fn ($lead) => $lead->created_at->toDateString());

        return [
            'datasets' => [[
                'label' => 'Lead masuk',
                'data' => $days->map(fn ($day) => $counts->get($day->toDateString(), 0))->all(),
                'borderColor' => '#CC0000',
                'backgroundColor' => 'rgba(204, 0, 0, 0.12)',
                'fill' => true,
                'tension' => 0.3,
            ]],
            'labels' => $days->map(fn ($day) => $day->format('d M'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
