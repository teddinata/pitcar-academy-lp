<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Class purchases, for handing access to buyers by WhatsApp or email.
 * Orders come from the checkout; nothing here creates or edits one.
 */
class OrderResource extends Resource
{
    /** How far back the "Belum bayar" badges count orders nobody has messaged yet. */
    public const FOLLOW_UP_DAYS = 7;

    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Kelas online';

    protected static ?string $modelLabel = 'Pesanan kelas';

    protected static ?string $pluralModelLabel = 'Pesanan kelas';

    protected static ?int $navigationSort = 2;

    // Buyers' contact details: managers and admins only, like the full lead list.
    public static function canViewAny(): bool
    {
        return auth()->user()?->seesAllLeads() ?? false;
    }

    /** Recent orders nobody has paid for or messaged yet, so the follow-up list is visible from any page. */
    public static function getNavigationBadge(): ?string
    {
        $count = Order::unpaid()->whereNull('followed_up_at')->where('created_at', '>=', now()->subDays(self::FOLLOW_UP_DAYS))->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Belum bayar, belum di-follow up';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['student', 'course', 'items.course', 'latestWhatsApp', 'followedUpBy']);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
        ];
    }
}
