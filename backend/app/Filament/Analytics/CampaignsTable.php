<?php

namespace App\Filament\Analytics;

use App\Services\Analytics\MetaAds;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class CampaignsTable extends TableWidget
{
    use ReadsReport;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $configured = app(MetaAds::class)->configured();

        return $table
            ->heading('Kampanye Meta')
            ->description('Pembelian dan biaya per pembelian menurut Meta (pixel dan Conversions API). Angkanya bisa berbeda dari penjualan yang tercatat di atas.')
            ->records(fn () => collect($this->report()->campaigns())->keyBy('key')->all())
            ->paginated(false)
            ->emptyStateHeading($configured ? 'Tidak ada biaya iklan di rentang ini' : 'Belum terhubung ke Meta')
            ->emptyStateDescription($configured ? null : 'Isi Ad Account ID dan token di Pengaturan › Iklan Meta.')
            ->columns([
                TextColumn::make('name')->label('Kampanye')->wrap(),
                TextColumn::make('spend')->label('Biaya')->formatStateUsing(fn ($state) => Format::rupiah((float) $state))->alignEnd(),
                TextColumn::make('clicks')->label('Klik link')->numeric(locale: 'id')->alignEnd(),
                TextColumn::make('cpc')->label('CPC')->formatStateUsing(fn ($state) => Format::rupiah($state))->placeholder('-')->alignEnd(),
                TextColumn::make('purchases')->label('Pembelian')->numeric(locale: 'id')->alignEnd(),
                TextColumn::make('cost_per_purchase')->label('Biaya per pembelian')->formatStateUsing(fn ($state) => Format::rupiah($state))->placeholder('-')->alignEnd(),
            ]);
    }
}
