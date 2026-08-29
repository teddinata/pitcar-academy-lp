<?php

namespace App\Filament\Resources\EducationConsultants;

use App\Filament\Resources\EducationConsultants\Pages\ListEducationConsultants;
use App\Models\EducationConsultant;
use App\Models\User;
use App\Rules\IndonesianWhatsAppNumber;
use App\Support\LeadOptions;
use App\Support\WhatsAppNumber;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EducationConsultantResource extends Resource
{
    protected static ?string $model = EducationConsultant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $modelLabel = 'Education Consultant';

    protected static ?string $pluralModelLabel = 'Education Consultants';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()?->seesAllLeads() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nama')
                ->required()
                ->maxLength(100),

            TextInput::make('whatsapp_number')
                ->label('Nomor WhatsApp')
                ->required()
                ->maxLength(20)
                ->helperText('Format 62xxxxxxxxxx. Nomor ini yang dipakai membuat link wa.me.')
                // Normalised on the way in so routing and links never depend on
                // how someone typed it.
                ->dehydrateStateUsing(fn (?string $state) => WhatsAppNumber::normalize($state) ?? $state)
                ->rule(new IndonesianWhatsAppNumber),

            Select::make('user_id')
                ->label('Akun dashboard')
                ->options(fn () => User::query()->pluck('name', 'id')->all())
                ->searchable()
                ->placeholder('Belum punya akun')
                ->helperText('Tautkan agar consultant bisa login dan melihat lead-nya sendiri.'),

            Toggle::make('is_active')
                ->label('Aktif')
                ->default(true)
                ->helperText('Consultant nonaktif tidak menerima lead baru.'),

            Select::make('programs')
                ->label('Program yang ditangani')
                ->multiple()
                ->options(LeadOptions::PROGRAMS)
                ->helperText('Kosongkan berarti menangani semua program.'),

            TagsInput::make('domiciles')
                ->label('Domisili yang ditangani')
                ->placeholder('Tambah kota')
                ->helperText('Kosongkan berarti menangani semua domisili.'),

            TextInput::make('max_active_leads')
                ->label('Maksimum lead aktif')
                ->numeric()
                ->default(50)
                ->minValue(1),

            TextInput::make('priority')
                ->label('Prioritas routing')
                ->numeric()
                ->default(10)
                ->helperText('Angka lebih kecil dipilih lebih dulu.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('whatsapp_number')->label('WhatsApp')->searchable(),
                TextColumn::make('user.name')->label('Akun')->placeholder('—'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('leads_count')
                    ->label('Lead aktif')
                    ->counts(['leads' => fn ($q) => $q->whereIn('status', ['new', 'assigned', 'contacted', 'consultation', 'qualified', 'nurture'])])
                    ->badge(),
                TextColumn::make('max_active_leads')->label('Kapasitas'),
                TextColumn::make('priority')->label('Prioritas')->sortable(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => auth()->user()?->isAdmin() ?? false),
                ]),
            ])
            ->headerActions([CreateAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEducationConsultants::route('/'),
        ];
    }
}
