<?php

namespace App\Filament\Resources\Courses\Schemas;

use App\Enums\CourseStatus;
use App\Filament\Resources\Courses\CourseResource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CourseForm
{
    public static function configure(Schema $schema): Schema
    {
        $prices = CourseResource::canEditPrices();

        return $schema->components([
            Section::make('Info kelas')
                ->columns(2)
                ->schema([
                    TextInput::make('title')
                        ->label('Judul')
                        ->required()
                        ->maxLength(150)
                        ->columnSpanFull()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (string $operation, ?string $state, callable $set) {
                            if ($operation === 'create') {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),

                    // The slug is the sales page URL, the checkout ?kelas= value and the
                    // key the landing site reads prices by. Changing it breaks all three.
                    TextInput::make('slug')
                        ->label('Slug (alamat halaman)')
                        ->required()
                        ->maxLength(120)
                        ->alphaDash()
                        ->unique(ignoreRecord: true)
                        ->disabledOn('edit')
                        ->helperText('Terkunci setelah dibuat: dipakai URL halaman, checkout, dan link iklan.'),

                    TextInput::make('tracking_id')
                        ->label('ID produk untuk iklan')
                        ->maxLength(120)
                        ->alphaDash()
                        ->disabledOn('edit')
                        ->helperText('content_ids di Meta Pixel dan item_id di GA4. Kosong berarti sama dengan slug. Terkunci setelah dibuat.'),

                    Textarea::make('summary')
                        ->label('Ringkasan')
                        ->rows(3)
                        ->maxLength(500)
                        ->columnSpanFull(),

                    Select::make('status')
                        ->label('Status')
                        ->options([
                            CourseStatus::Draft->value => 'Draft (tidak dijual)',
                            CourseStatus::Published->value => 'Terbit (dijual di checkout)',
                        ])
                        ->default(CourseStatus::Draft->value)
                        ->required()
                        ->native(false),
                ]),

            Section::make('Harga')
                ->description($prices
                    ? 'Checkout langsung menagih harga baru setelah disimpan. Landing page ikut berubah setelah situs diterbitkan ulang.'
                    : 'Hanya admin yang bisa mengubah harga.')
                ->columns(2)
                ->schema([
                    TextInput::make('price')
                        ->label('Harga')
                        ->prefix('Rp')
                        ->numeric()
                        ->integer()
                        ->minValue(1000)
                        ->maxValue(100_000_000)
                        ->required()
                        ->disabled(! $prices)
                        ->helperText('Nominal yang ditagih DOKU.'),

                    TextInput::make('strike_price')
                        ->label('Harga coret')
                        ->prefix('Rp')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->maxValue(100_000_000)
                        ->gt('price')
                        ->disabled(! $prices)
                        ->helperText('Harga normal yang dicoret. Kosongkan kalau tidak ada.'),
                ]),
        ]);
    }
}
