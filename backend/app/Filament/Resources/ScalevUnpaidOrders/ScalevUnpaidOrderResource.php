<?php

namespace App\Filament\Resources\ScalevUnpaidOrders;

use App\Filament\Resources\ScalevUnpaidOrders\Pages\ListScalevUnpaidOrders;
use App\Filament\Resources\ScalevUnpaidOrders\Tables\ScalevUnpaidOrdersTable;
use App\Models\ScalevUnpaidOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Orders left unpaid on Scalev in the last week, to send to the new
 * checkout. Rows come from the hourly Scalev sync; nothing here edits one
 * beyond noting the follow-up.
 */
class ScalevUnpaidOrderResource extends Resource
{
    protected static ?string $model = ScalevUnpaidOrder::class;

    protected static ?string $slug = 'pesanan-scalev';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Kelas online';

    protected static ?string $navigationLabel = 'Belum bayar di Scalev';

    protected static ?string $modelLabel = 'Pesanan Scalev';

    protected static ?string $pluralModelLabel = 'Belum bayar di Scalev';

    protected static ?int $navigationSort = 3;

    // Buyers' contact details: same people who see the class orders.
    public static function canViewAny(): bool
    {
        return auth()->user()?->seesAllLeads() ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = ScalevUnpaidOrder::toFollowUp()->whereNull('followed_up_at')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Belum di-follow up';
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
        return parent::getEloquentQuery()->toFollowUp()->with(['course', 'followedUpBy']);
    }

    public static function table(Table $table): Table
    {
        return ScalevUnpaidOrdersTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListScalevUnpaidOrders::route('/')];
    }
}
