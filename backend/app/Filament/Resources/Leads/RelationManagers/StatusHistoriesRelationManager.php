<?php

namespace App\Filament\Resources\Leads\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StatusHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'statusHistories';

    protected static ?string $title = 'Riwayat status';

    public function table(Table $table): Table
    {
        // Read-only by design: an audit trail that can be edited is not one.
        return $table
            ->recordTitleAttribute('to_status')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('from_status')
                    ->label('Dari')
                    ->placeholder('—')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('to_status')
                    ->label('Ke')
                    ->badge(),
                TextColumn::make('changed_by')
                    ->label('Oleh'),
                TextColumn::make('notes')
                    ->label('Catatan')
                    ->placeholder('—')
                    ->wrap(),
            ]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
