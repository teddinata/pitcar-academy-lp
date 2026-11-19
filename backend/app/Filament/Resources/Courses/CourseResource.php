<?php

namespace App\Filament\Resources\Courses;

use App\Filament\Resources\Courses\Pages\CreateCourse;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Filament\Resources\Courses\Pages\ListCourses;
use App\Filament\Resources\Courses\RelationManagers\AttachmentsRelationManager;
use App\Filament\Resources\Courses\RelationManagers\ModulesRelationManager;
use App\Filament\Resources\Courses\RelationManagers\OffersRelationManager;
use App\Filament\Resources\Courses\Schemas\CourseForm;
use App\Filament\Resources\Courses\Tables\CoursesTable;
use App\Models\Course;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Online classes sold through our own checkout: price, curriculum, files and
 * the bundle offered at checkout. The database is the one source; the
 * checkout reads it live and the landing site at build time.
 */
class CourseResource extends Resource
{
    protected static ?string $model = Course::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlayCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Kelas online';

    protected static ?string $navigationLabel = 'Produk online';

    protected static ?string $modelLabel = 'kelas online';

    protected static ?string $pluralModelLabel = 'Produk online';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 1;

    // Admins and managers run the catalogue; consultants only work leads.
    public static function canViewAny(): bool
    {
        return auth()->user()?->seesAllLeads() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    // A sold course stays: orders and enrollments point at it. Set it to Draft instead.
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /** Only an admin changes what buyers are charged. */
    public static function canEditPrices(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return CourseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CoursesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ModulesRelationManager::class,
            AttachmentsRelationManager::class,
            OffersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCourses::route('/'),
            'create' => CreateCourse::route('/create'),
            'edit' => EditCourse::route('/{record}/edit'),
        ];
    }
}
