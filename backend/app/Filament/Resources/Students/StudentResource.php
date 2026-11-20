<?php

namespace App\Filament\Resources\Students;

use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Resources\Students\Tables\StudentsTable;
use App\Models\Student;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Every student across all classes, with when they last logged in and last
 * studied. Read only: accounts are created by payment or the Scalev import.
 */
class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'Kelas online';

    protected static ?string $navigationLabel = 'Siswa';

    protected static ?string $modelLabel = 'siswa';

    protected static ?string $pluralModelLabel = 'Siswa';

    protected static ?int $navigationSort = 2;

    // Names, emails and numbers: managers and admins only, like the order list.
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['enrollments' => fn ($q) => $q->whereNull('revoked_at')->with('course:id,title')]);
    }

    public static function table(Table $table): Table
    {
        return StudentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudents::route('/'),
        ];
    }
}
