<?php

namespace App\Filament\Resources\Discussions\RelationManagers;

use App\Filament\Resources\Discussions\DiscussionResource;
use App\Filament\Resources\Discussions\ModerationActions;
use App\Models\DiscussionPost;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RepliesRelationManager extends RelationManager
{
    protected static string $relationship = 'replies';

    protected static ?string $title = 'Balasan';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['student', 'user', 'replyTo.student', 'replyTo.user', 'deletedBy'])
                ->withCount(['reports as open_reports' => fn ($r) => $r->whereNull('resolved_at')]))
            ->defaultSort('id')
            ->paginated([25, 50])
            ->emptyStateHeading('Belum ada balasan')
            ->columns([
                TextColumn::make('author')
                    ->label('Dari')
                    ->state(fn (DiscussionPost $record) => $record->authorName())
                    ->badge(fn (DiscussionPost $record) => $record->user_id !== null)
                    ->color(fn (DiscussionPost $record) => $record->user_id ? 'primary' : null)
                    ->description(fn (DiscussionPost $record) => $record->replyTo ? 'membalas '.$record->replyTo->authorName() : null),

                TextColumn::make('body')
                    ->label('Isi')
                    ->wrap()
                    ->color(fn (DiscussionPost $record) => $record->isRemoved() ? 'gray' : null)
                    ->description(fn (DiscussionPost $record) => match (true) {
                        $record->isRemoved() => 'Dihapus '.DiscussionResource::removalNote($record),
                        $record->open_reports > 0 => DiscussionResource::reportNote($record),
                        default => null,
                    }),

                TextColumn::make('flag')
                    ->label('Status')
                    ->badge()
                    ->state(fn (DiscussionPost $record) => match (true) {
                        $record->isRemoved() => 'Dihapus',
                        $record->open_reports > 0 => 'Dilaporkan',
                        default => null,
                    })
                    ->color(fn (?string $state) => $state === 'Dilaporkan' ? 'danger' : 'gray'),

                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->since()
                    ->dateTimeTooltip('d M Y H:i'),
            ])
            ->headerActions([
                ModerationActions::reply(fn () => $this->getOwnerRecord())->label('Balas pertanyaan'),
            ])
            ->recordActions([
                ModerationActions::reply()->label('Balas'),
                ModerationActions::dismissReports(),
                ModerationActions::remove(),
            ]);
    }
}
