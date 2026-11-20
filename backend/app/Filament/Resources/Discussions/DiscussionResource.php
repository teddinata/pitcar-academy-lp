<?php

namespace App\Filament\Resources\Discussions;

use App\Filament\Resources\Discussions\Pages\ListDiscussions;
use App\Filament\Resources\Discussions\Pages\ViewDiscussion;
use App\Filament\Resources\Discussions\RelationManagers\RepliesRelationManager;
use App\Filament\Resources\Discussions\Tables\DiscussionsTable;
use App\Models\DiscussionPost;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Class discussions: questions to answer and reported messages to check.
 * Each record is a thread; its replies are on the thread's page.
 */
class DiscussionResource extends Resource
{
    protected static ?string $model = DiscussionPost::class;

    protected static ?string $slug = 'diskusi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Kelas online';

    protected static ?string $navigationLabel = 'Diskusi';

    protected static ?string $modelLabel = 'Diskusi';

    protected static ?string $pluralModelLabel = 'Diskusi';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return auth()->user()?->seesAllLeads() ?? false;
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

    /** Reported messages first, in red; otherwise questions nobody from the team has answered. */
    public static function getNavigationBadge(): ?string
    {
        $count = self::reportedCount() ?: DiscussionPost::unanswered()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return self::reportedCount() > 0 ? 'danger' : 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return self::reportedCount() > 0 ? 'Pesan dilaporkan siswa' : 'Pertanyaan belum dijawab tim';
    }

    public static function reportedCount(): int
    {
        return once(fn () => DiscussionPost::reported()->count());
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->threads()
            ->with(['course', 'lesson', 'student', 'user'])
            ->withExists(['replies as answered' => fn ($q) => $q->whereNotNull('user_id')->whereNull('deleted_at')]);
    }

    public static function table(Table $table): Table
    {
        return DiscussionsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('body')
                        ->hiddenLabel()
                        ->size('lg')
                        ->formatStateUsing(fn (DiscussionPost $record, $state) => $record->isRemoved() ? 'Dihapus: '.$state : $state)
                        ->color(fn (DiscussionPost $record) => $record->isRemoved() ? 'gray' : null),
                    TextEntry::make('author')
                        ->label('Dari')
                        ->state(fn (DiscussionPost $record) => $record->authorName().($record->student ? ' ('.$record->student->email.')' : ''))
                        ->inlineLabel(),
                    TextEntry::make('course.title')->label('Kelas')->inlineLabel(),
                    TextEntry::make('lesson.title')->label('Video')->placeholder('Kelas secara umum')->inlineLabel(),
                    TextEntry::make('created_at')->label('Ditulis')->dateTime('d M Y H:i')->inlineLabel(),
                    TextEntry::make('removal')
                        ->label('Dihapus')
                        ->inlineLabel()
                        ->visible(fn (DiscussionPost $record) => $record->isRemoved())
                        ->state(fn (DiscussionPost $record) => self::removalNote($record)),
                    TextEntry::make('open_reports')
                        ->label('Laporan siswa')
                        ->inlineLabel()
                        ->color('danger')
                        ->visible(fn (DiscussionPost $record) => $record->reports()->whereNull('resolved_at')->exists())
                        ->state(fn (DiscussionPost $record) => self::reportNote($record)),
                ]),
        ]);
    }

    public static function removalNote(DiscussionPost $post): string
    {
        $reason = $post->delete_reason === DiscussionPost::REASON_AUTHOR
            ? 'oleh penulisnya sendiri'
            : (DiscussionPost::REASONS[$post->delete_reason] ?? 'oleh tim').($post->deletedBy ? ', oleh '.$post->deletedBy->name : '');

        return $post->deleted_at->timezone(config('app.display_timezone'))->format('d M Y H:i').', '.$reason;
    }

    public static function reportNote(DiscussionPost $post): string
    {
        $reports = $post->reports()->whereNull('resolved_at')->get();

        return $reports->count().' laporan'.(($reasons = $reports->pluck('reason')->filter()->implode('; ')) ? ': '.$reasons : '');
    }

    public static function getRelations(): array
    {
        return [RepliesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDiscussions::route('/'),
            'view' => ViewDiscussion::route('/{record}'),
        ];
    }
}
