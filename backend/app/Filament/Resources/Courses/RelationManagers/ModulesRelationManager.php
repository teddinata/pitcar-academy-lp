<?php

namespace App\Filament\Resources\Courses\RelationManagers;

use App\Http\Controllers\Api\LearnController;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Kurikulum: modules in order, each holding its videos in order. */
class ModulesRelationManager extends RelationManager
{
    protected static string $relationship = 'modules';

    protected static ?string $title = 'Kurikulum';

    protected static ?string $modelLabel = 'modul';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label('Judul modul')
                ->required()
                ->maxLength(150)
                ->columnSpanFull(),

            Repeater::make('lessons')
                ->label('Video')
                ->relationship()
                ->orderColumn('sort')
                ->reorderableWithButtons()
                ->collapsible()
                ->itemLabel(fn (array $state) => $state['title'] ?? null)
                ->addActionLabel('Tambah video')
                ->defaultItems(0)
                ->columnSpanFull()
                ->columns(2)
                ->helperText('Menghapus video juga menghapus progres siswa di video itu.')
                ->schema([
                    TextInput::make('title')
                        ->label('Judul video')
                        ->required()
                        ->maxLength(150)
                        ->columnSpanFull(),

                    TextInput::make('youtube_url')
                        ->label('Link YouTube')
                        ->url()
                        ->maxLength(255)
                        ->placeholder('https://youtu.be/...')
                        ->helperText('Kosongkan kalau videonya belum siap; siswa melihat "belum diunggah".')
                        ->rule(fn () => function (string $attribute, $value, $fail) {
                            if (filled($value) && LearnController::youtubeId($value) === null) {
                                $fail('Bukan link video YouTube. Contoh: https://youtu.be/abc123xyz00');
                            }
                        }),

                    TextInput::make('duration_seconds')
                        ->label('Durasi (menit:detik)')
                        ->placeholder('12:30')
                        ->regex('/^\d{1,3}:[0-5]\d$/')
                        ->validationMessages(['regex' => 'Tulis sebagai menit:detik, misalnya 12:30.'])
                        ->formatStateUsing(fn ($state) => $state ? intdiv((int) $state, 60).':'.str_pad((string) ((int) $state % 60), 2, '0', STR_PAD_LEFT) : null)
                        ->dehydrateStateUsing(function ($state) {
                            if (blank($state)) {
                                return null;
                            }
                            [$minutes, $seconds] = array_map('intval', explode(':', (string) $state));

                            return $minutes * 60 + $seconds;
                        }),

                    Textarea::make('summary')
                        ->label('Ringkasan')
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull(),

                    Toggle::make('is_preview')
                        ->label('Bisa ditonton sebagai cuplikan')
                        ->default(false),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('lessons'))
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('title')->label('Modul')->weight('bold')->wrap(),
                TextColumn::make('lessons_count')->label('Video'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah modul')
                    // New modules go to the end of the list.
                    ->mutateDataUsing(fn (array $data) => $data + ['sort' => (int) $this->getOwnerRecord()->modules()->max('sort') + 1]),
            ])
            ->recordActions([
                EditAction::make()->label('Ubah isi'),
                DeleteAction::make()
                    ->modalDescription('Semua video di modul ini dan progres siswa pada video-video itu ikut terhapus.'),
            ]);
    }
}
