<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Leads\LeadResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LeadStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // Reuses the resource query so a consultant sees stats for their own
        // leads rather than the whole pipeline.
        $base = fn () => LeadResource::getEloquentQuery();

        $today = $base()->whereDate('created_at', today())->count();
        $last7 = $base()->where('created_at', '>=', now()->subDays(7))->count();
        $previous7 = $base()
            ->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])
            ->count();

        $openHot = $base()
            ->where('qualification', 'hot')
            ->whereNotIn('status', ['converted', 'registered', 'lost', 'invalid'])
            ->count();

        $overdue = $base()
            ->whereNotNull('follow_up_due_at')
            ->where('follow_up_due_at', '<', now())
            ->whereNotIn('status', ['converted', 'registered', 'lost', 'invalid'])
            ->count();

        $total = $base()->count();
        $converted = $base()->where('status', 'converted')->count();
        $rate = $total > 0 ? round($converted / $total * 100, 1) : 0.0;

        return [
            Stat::make('Lead hari ini', (string) $today)
                ->description($last7.' dalam 7 hari terakhir')
                ->descriptionIcon($last7 >= $previous7 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($last7 >= $previous7 ? 'success' : 'warning'),

            Stat::make('Hot belum selesai', (string) $openHot)
                ->description('Prioritas follow-up')
                ->color($openHot > 0 ? 'danger' : 'gray'),

            Stat::make('SLA terlewat', (string) $overdue)
                ->description('Lewat batas follow-up')
                ->color($overdue > 0 ? 'danger' : 'success'),

            Stat::make('Konversi', $rate.'%')
                ->description($converted.' dari '.$total.' lead')
                ->color('info'),
        ];
    }
}
