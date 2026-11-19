<?php

namespace App\Filament\Resources\Courses\RelationManagers;

use App\Filament\Resources\Courses\CourseResource;
use App\Models\Course;
use App\Models\CourseOffer;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

/** Bundle: the classes offered as an add-on at this class's checkout. */
class OffersRelationManager extends RelationManager
{
    protected static string $relationship = 'offers';

    protected static ?string $title = 'Bundle di checkout';

    protected static ?string $modelLabel = 'tawaran bundle';

    public function form(Schema $schema): Schema
    {
        $prices = CourseResource::canEditPrices();
        $owner = $this->getOwnerRecord();

        return $schema->components([
            Select::make('offer_course_id')
                ->label('Kelas yang ditawarkan')
                ->options(fn () => Course::whereKeyNot($owner->getKey())->orderBy('title')->pluck('title', 'id'))
                ->required()
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('course_id', $owner->getKey()))
                ->validationMessages(['unique' => 'Kelas ini sudah ditawarkan di checkout kelas ini.'])
                ->live(),

            TextInput::make('price')
                ->label('Harga tambahan')
                ->prefix('Rp')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->required()
                ->disabled(! $prices)
                ->helperText(function (callable $get) use ($prices) {
                    $normal = Course::find($get('offer_course_id'))?->price;
                    $hint = $normal ? 'Harga satuan kelas itu Rp'.number_format($normal, 0, ',', '.').'.' : '';

                    return trim('Yang ditambahkan ke total saat pembeli mencentang tawaran ini. '.$hint.($prices ? '' : ' Hanya admin yang bisa mengubah.'));
                }),

            TextInput::make('headline')
                ->label('Kalimat ajakan')
                ->maxLength(150)
                ->placeholder('Mau beli mobil bekas? Pelajari cara memeriksanya sebelum membayar.')
                ->columnSpanFull(),

            Toggle::make('is_active')
                ->label('Tampilkan di checkout')
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('offerCourse'))
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('offerCourse.title')->label('Kelas yang ditawarkan')->weight('bold')->wrap(),
                TextColumn::make('price')
                    ->label('Harga tambahan')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->description(fn (CourseOffer $record) => $record->offerCourse && $record->offerCourse->price > $record->price
                        ? 'hemat Rp'.number_format($record->offerCourse->price - $record->price, 0, ',', '.').' dari harga satuan'
                        : null),
                TextColumn::make('headline')->label('Kalimat ajakan')->wrap()->placeholder('—'),
                IconColumn::make('is_active')->label('Tampil')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->label('Tambah tawaran'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
