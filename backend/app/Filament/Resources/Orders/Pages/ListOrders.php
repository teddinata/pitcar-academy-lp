<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function getTabs(): array
    {
        return [
            'paid' => Tab::make('Lunas')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::Paid)),
            // Ordered, never paid, and still without the class: the follow-up list.
            'unpaid' => Tab::make('Belum bayar')
                ->modifyQueryUsing(fn (Builder $query) => $query->unpaid())
                ->badge(fn () => Order::unpaid()->whereNull('followed_up_at')->where('created_at', '>=', now()->subDays(OrderResource::FOLLOW_UP_DAYS))->count() ?: null)
                ->badgeColor('warning')
                ->badgeTooltip('Belum di-follow up, '.OrderResource::FOLLOW_UP_DAYS.' hari terakhir'),
            'all' => Tab::make('Semua'),
        ];
    }

    public function getDefaultActiveTab(): string
    {
        return 'paid';
    }
}
