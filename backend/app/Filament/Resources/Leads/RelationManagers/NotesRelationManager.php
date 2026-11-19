<?php

namespace App\Filament\Resources\Leads\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    protected static ?string $title = 'Catatan konsultasi';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('note')
                ->label('Catatan')
                ->required()
                ->rows(4)
                ->maxLength(2000)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('note')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('note')
                    ->label('Catatan')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('author.name')
                    ->label('Oleh')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah catatan')
                    // Authorship is stamped server-side so a note cannot be
                    // attributed to someone else.
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'user_id' => auth()->id(),
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn ($record) => $record->user_id === auth()->id()),
                DeleteAction::make()
                    ->visible(fn ($record) => auth()->user()?->isAdmin() ?? false),
            ]);
    }
}
