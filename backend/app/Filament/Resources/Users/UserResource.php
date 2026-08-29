<?php

namespace App\Filament\Resources\Users;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $modelLabel = 'Pengguna';

    protected static ?string $pluralModelLabel = 'Pengguna';

    protected static ?int $navigationSort = 3;

    /** Only an admin manages who can see prospect data. */
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama')->required()->maxLength(100),

            TextInput::make('email')
                ->label('Email')
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(150),

            Select::make('role')
                ->label('Role')
                ->options(UserRole::options())
                ->default(UserRole::Consultant->value)
                ->required()
                ->helperText('Consultant hanya melihat lead yang di-assign kepadanya.'),

            TextInput::make('password')
                ->label('Password')
                ->password()
                ->revealable()
                ->minLength(12)
                ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null)
                // Leaving it blank on edit keeps the existing password.
                ->dehydrated(fn (?string $state) => filled($state))
                ->required(fn (string $operation) => $operation === 'create')
                ->helperText('Minimal 12 karakter. Kosongkan saat edit untuk mempertahankan password lama.'),

            Toggle::make('is_active')
                ->label('Aktif')
                ->default(true)
                ->helperText('Menonaktifkan langsung mencabut akses dashboard.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn (UserRole $state) => $state->label())
                    ->color(fn (UserRole $state) => match ($state) {
                        UserRole::Admin => 'danger',
                        UserRole::Manager => 'warning',
                        UserRole::Consultant => 'info',
                    }),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('consultant.name')->label('Consultant')->placeholder('—'),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y')->sortable(),
            ])
            ->recordActions([EditAction::make()])
            ->headerActions([CreateAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
        ];
    }
}
