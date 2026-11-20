<?php

namespace App\Filament\Resources\CourseReviews;

use App\Filament\Resources\CourseReviews\Pages\ListCourseReviews;
use App\Filament\Resources\CourseReviews\Tables\CourseReviewsTable;
use App\Models\CourseReview;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Students' ratings of a class. Read only: the student owns the words. */
class CourseReviewResource extends Resource
{
    protected static ?string $model = CourseReview::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|UnitEnum|null $navigationGroup = 'Kelas online';

    protected static ?string $modelLabel = 'Nilai kelas';

    protected static ?string $pluralModelLabel = 'Nilai kelas';

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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['student', 'course']);
    }

    public static function table(Table $table): Table
    {
        return CourseReviewsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCourseReviews::route('/'),
        ];
    }
}
