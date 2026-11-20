<?php

namespace App\Filament\Resources\Courses\Tables;

use App\Enums\CourseStatus;
use App\Models\Course;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CoursesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['lessons', 'attachments', 'enrollments', 'reviews'])->withAvg('reviews', 'rating')->with('offers.offerCourse'))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('title')
                    ->label('Kelas')
                    ->weight('bold')
                    ->wrap()
                    ->description(fn (Course $record) => $record->slug)
                    ->searchable(),

                TextColumn::make('price')
                    ->label('Harga')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->description(fn (Course $record) => $record->strike_price
                        ? 'coret Rp'.number_format($record->strike_price, 0, ',', '.')
                        : null),

                TextColumn::make('lessons_count')
                    ->label('Isi')
                    ->formatStateUsing(fn (Course $record) => "{$record->lessons_count} video, {$record->attachments_count} lampiran")
                    // A class on sale with no video yet is worth seeing at a glance.
                    ->color(fn (Course $record) => $record->lessons_count === 0 ? 'warning' : null),

                TextColumn::make('offers')
                    ->label('Bundle di checkout')
                    ->state(fn (Course $record) => $record->offers
                        ->where('is_active', true)
                        ->map(fn ($offer) => '+ '.$offer->offerCourse?->title.' (Rp'.number_format($offer->price, 0, ',', '.').')')
                        ->implode(', ') ?: '—')
                    ->wrap(),

                TextColumn::make('enrollments_count')
                    ->label('Siswa'),

                TextColumn::make('reviews_avg_rating')
                    ->label('Nilai')
                    ->state(fn (Course $record) => $record->reviews_count > 0
                        ? number_format((float) $record->reviews_avg_rating, 1, ',', '.').' dari '.$record->reviews_count.' siswa'
                        : 'Belum ada'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (CourseStatus $state) => $state === CourseStatus::Published ? 'Terbit' : 'Draft')
                    ->color(fn (CourseStatus $state) => $state === CourseStatus::Published ? 'success' : 'gray'),
            ])
            ->recordActions([EditAction::make()]);
    }
}
