<?php

namespace App\Filament\Pages;

use App\Filament\Analytics\AdSpendChart;
use App\Filament\Analytics\AnalyticsStats;
use App\Filament\Analytics\CampaignsTable;
use App\Filament\Analytics\LiveVisitors;
use App\Filament\Analytics\PagesTable;
use App\Filament\Analytics\SourcesTable;
use App\Filament\Analytics\VisitorsSalesChart;
use App\Models\AppSetting;
use App\Services\Analytics\MetaAds;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Online classes in one page: visitors and time on the site, sales from DOKU
 * and Scalev, and what Meta ads cost per sale.
 */
class OnlineAnalytics extends Dashboard
{
    use HasFiltersForm;

    protected static string $routePath = 'analitik';

    protected static ?string $title = 'Analitik kelas online';

    protected static ?string $navigationLabel = 'Analitik';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Kelas online';

    protected static ?int $navigationSort = 0;

    public static function canAccess(): bool
    {
        return auth()->user()?->seesAllLeads() ?? false;
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('range')
                ->label('Rentang')
                ->options([
                    '7' => '7 hari terakhir',
                    '30' => '30 hari terakhir',
                    '90' => '90 hari terakhir',
                    'this_month' => 'Bulan ini',
                    'last_month' => 'Bulan lalu',
                ])
                ->default('30')
                ->selectablePlaceholder(false),
        ]);
    }

    public function getSubheading(): ?string
    {
        $meta = app(MetaAds::class);

        if (! $meta->configured()) {
            return 'Biaya iklan belum terhubung. Atur di Pengaturan › Iklan Meta.';
        }

        $error = AppSetting::get(MetaAds::LAST_ERROR);
        if ($error) {
            return 'Data Meta gagal diambil: '.$error;
        }

        $synced = AppSetting::get(MetaAds::SYNCED_AT);

        return $synced
            ? 'Biaya iklan dari Meta diambil tiap jam, terakhir '.CarbonImmutable::parse($synced)->setTimezone(config('app.display_timezone'))->format('d M H:i').' WIB.'
            : 'Biaya iklan dari Meta diambil tiap jam. Belum ada pengambilan pertama.';
    }

    public function getWidgets(): array
    {
        return [
            LiveVisitors::class,
            AnalyticsStats::class,
            VisitorsSalesChart::class,
            AdSpendChart::class,
            PagesTable::class,
            SourcesTable::class,
            CampaignsTable::class,
        ];
    }

    public function getColumns(): int|array
    {
        return ['md' => 2];
    }
}
