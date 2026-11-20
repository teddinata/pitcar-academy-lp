<?php

namespace App\Filament\Analytics;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class PagesTable extends TableWidget
{
    use ReadsReport;

    // Full width: four numeric columns plus a two-line label do not fit beside another table.
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 4;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Halaman paling banyak dikunjungi')
            ->records(fn () => collect($this->report()->pages())->keyBy('key')->all())
            ->paginated(false)
            ->emptyStateHeading('Belum ada kunjungan di rentang ini')
            ->emptyStateDescription('Kunjungan tercatat sejak update situs ini tayang.')
            ->columns([
                TextColumn::make('path')
                    ->label('Halaman')
                    ->formatStateUsing(fn (string $state) => Format::pageName($state))
                    ->description(fn (array $record) => Format::isNamedPage($record['path']) ? $record['path'] : null),
                TextColumn::make('visitors')->label('Pengunjung')->numeric(locale: 'id')->alignEnd(),
                TextColumn::make('seconds_per_visitor')->label('Rata-rata waktu')->formatStateUsing(fn ($state) => Format::duration((int) $state))->alignEnd(),
            ]);
    }
}
