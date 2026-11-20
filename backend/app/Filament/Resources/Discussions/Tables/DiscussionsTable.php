<?php

namespace App\Filament\Resources\Discussions\Tables;

use App\Filament\Resources\Discussions\DiscussionResource;
use App\Models\DiscussionPost;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DiscussionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('last_activity_at', 'desc')
            ->recordUrl(fn (DiscussionPost $record) => DiscussionResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Tidak ada diskusi di sini')
            ->columns([
                TextColumn::make('body')
                    ->label('Pertanyaan')
                    ->searchable()
                    ->wrap()
                    ->limit(160)
                    ->description(fn (DiscussionPost $record) => $record->course?->title.($record->lesson ? ' / '.$record->lesson->title : '')),

                TextColumn::make('author')
                    ->label('Dari')
                    ->state(fn (DiscussionPost $record) => $record->authorName())
                    ->description(fn (DiscussionPost $record) => $record->student?->email),

                TextColumn::make('reply_count')
                    ->label('Balasan')
                    ->numeric()
                    ->alignEnd(),

                TextColumn::make('state')
                    ->label('Status')
                    ->badge()
                    ->state(fn (DiscussionPost $record) => match (true) {
                        $record->isRemoved() => 'Dihapus',
                        (bool) $record->answered => 'Dijawab tim',
                        default => 'Belum dijawab',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Dijawab tim' => 'success',
                        'Belum dijawab' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('last_activity_at')
                    ->label('Aktivitas terakhir')
                    ->since()
                    ->dateTimeTooltip('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('course_id')->label('Kelas')->relationship('course', 'title'),
            ])
            ->recordActions([
                ViewAction::make()->label('Buka'),
            ]);
    }
}
