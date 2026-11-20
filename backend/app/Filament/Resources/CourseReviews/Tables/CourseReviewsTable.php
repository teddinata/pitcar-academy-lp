<?php

namespace App\Filament\Resources\CourseReviews\Tables;

use App\Models\CourseReview;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CourseReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('updated_at')
                    ->label('Terakhir diubah')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('course.title')
                    ->label('Kelas')
                    ->limit(32),

                TextColumn::make('student.name')
                    ->label('Siswa')
                    ->searchable()
                    ->description(fn (CourseReview $record) => $record->student->email),

                TextColumn::make('rating')
                    ->label('Nilai')
                    ->formatStateUsing(fn (int $state) => "{$state} / 5")
                    ->weight('bold')
                    // The low ones are the ones to read first.
                    ->color(fn (int $state) => $state <= 2 ? 'danger' : null)
                    ->sortable(),

                TextColumn::make('body')
                    ->label('Ulasan')
                    ->placeholder('Tanpa ulasan')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('may_publish')
                    ->label('Boleh ditampilkan')
                    ->formatStateUsing(fn (bool $state) => $state ? 'Boleh' : 'Tidak')
                    ->color(fn (bool $state) => $state ? 'success' : 'gray')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('course_id')
                    ->label('Kelas')
                    ->relationship('course', 'title'),

                SelectFilter::make('rating')
                    ->label('Nilai')
                    ->options([5 => '5', 4 => '4', 3 => '3', 2 => '2', 1 => '1']),

                TernaryFilter::make('may_publish')
                    ->label('Boleh ditampilkan'),
            ]);
    }
}
