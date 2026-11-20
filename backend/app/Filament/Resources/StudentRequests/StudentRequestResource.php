<?php

namespace App\Filament\Resources\StudentRequests;

use App\Enums\RequestStatus;
use App\Filament\Resources\StudentRequests\Pages\ListStudentRequests;
use App\Filament\Resources\StudentRequests\Tables\StudentRequestsTable;
use App\Models\StudentRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Lesson ideas, feedback and problems sent from the learning area.
 * Students write them; the team only sets a status and replies.
 */
class StudentRequestResource extends Resource
{
    protected static ?string $model = StudentRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Kelas online';

    protected static ?string $modelLabel = 'Masukan siswa';

    protected static ?string $pluralModelLabel = 'Masukan siswa';

    protected static ?int $navigationSort = 3;

    // Carries students' names and emails, like the order list.
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

    /** Unanswered messages, so a new one is visible from any admin page. */
    public static function getNavigationBadge(): ?string
    {
        $count = StudentRequest::where('status', RequestStatus::New)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Belum ditanggapi';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['student', 'course', 'repliedBy']);
    }

    public static function table(Table $table): Table
    {
        return StudentRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudentRequests::route('/'),
        ];
    }
}
