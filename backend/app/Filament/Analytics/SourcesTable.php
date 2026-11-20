<?php

namespace App\Filament\Analytics;

use App\Support\TrafficSource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class SourcesTable extends TableWidget
{
    use ReadsReport;

    // Full width: four numeric columns plus a two-line label do not fit beside another table.
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 5;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Asal pengunjung')
            ->description('Penjualan di sini hanya dari checkout DOKU. Order Scalev tidak membawa data asal.')
            ->records(fn () => collect($this->report()->sources())->keyBy('key')->all())
            ->paginated(false)
            ->emptyStateHeading('Belum ada kunjungan di rentang ini')
            ->columns([
                TextColumn::make('source')->label('Sumber')->description(fn (array $record) => TrafficSource::hint($record['key'])),
                TextColumn::make('visitors')->label('Pengunjung')->numeric(locale: 'id')->alignEnd(),
                TextColumn::make('sales')->label('Penjualan')->numeric(locale: 'id')->alignEnd(),
                TextColumn::make('conversion')->label('Konversi')->formatStateUsing(fn ($state) => Format::percent($state))->placeholder('-')->alignEnd(),
            ]);
    }
}
