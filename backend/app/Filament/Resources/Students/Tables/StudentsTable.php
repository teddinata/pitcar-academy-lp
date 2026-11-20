<?php

namespace App\Filament\Resources\Students\Tables;

use App\Models\Course;
use App\Models\LessonProgress;
use App\Models\Student;
use App\Models\StudentSession;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->description('Login terakhir: saat siswa masuk dengan password atau link email. Terakhir aktif: terakhir membuka area belajar, dicatat paling sering sekali per jam. Terakhir belajar: terakhir memutar video.')
            ->modifyQueryUsing(fn (Builder $query) => $query->addSelect([
                'last_active_at' => StudentSession::query()
                    ->whereColumn('student_sessions.student_id', 'students.id')
                    ->selectRaw('max(coalesce(last_used_at, created_at))'),
                'last_learned_at' => LessonProgress::query()
                    ->whereColumn('lesson_progress.student_id', 'students.id')
                    ->selectRaw('max(updated_at)'),
            ]))
            ->defaultSort('last_login_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Siswa')
                    ->searchable(['name', 'email'])
                    ->weight('bold')
                    ->description(fn (Student $record) => $record->email),

                TextColumn::make('whatsapp')
                    ->label('WhatsApp')
                    ->searchable()
                    ->copyable()
                    ->placeholder('Tidak ada'),

                TextColumn::make('classes')
                    ->label('Kelas')
                    ->state(fn (Student $record) => $record->enrollments->map(fn ($e) => $e->course?->title)->filter()->values()->all())
                    ->listWithLineBreaks()
                    ->placeholder('Belum punya kelas'),

                TextColumn::make('last_login_at')
                    ->label('Login terakhir')
                    ->since()
                    ->dateTimeTooltip('d M Y H:i')
                    ->placeholder('Belum pernah login')
                    ->sortable(),

                TextColumn::make('last_active_at')
                    ->label('Terakhir aktif')
                    ->since()
                    ->dateTimeTooltip('d M Y H:i')
                    ->placeholder('Belum pernah')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('last_active_at', $direction)),

                TextColumn::make('last_learned_at')
                    ->label('Terakhir belajar')
                    ->since()
                    ->dateTimeTooltip('d M Y H:i')
                    ->placeholder('Belum mulai')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('last_learned_at', $direction)),

                TextColumn::make('created_at')
                    ->label('Terdaftar')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('course')
                    ->label('Kelas')
                    ->options(fn () => Course::query()->orderBy('title')->pluck('title', 'id')->all())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $q, $id) => $q
                        ->whereHas('enrollments', fn (Builder $e) => $e->where('course_id', $id)->whereNull('revoked_at')))),

                // Paid, got access, never came in: the first ones to message.
                Filter::make('never_logged_in')
                    ->label('Belum pernah login')
                    ->query(fn (Builder $query) => $query->whereNull('last_login_at')),

                Filter::make('away')
                    ->label('Tidak login 14 hari terakhir')
                    ->query(fn (Builder $query) => $query->where(fn (Builder $q) => $q
                        ->whereNull('last_login_at')
                        ->orWhere('last_login_at', '<', now()->subDays(14)))),
            ])
            ->emptyStateHeading('Belum ada siswa')
            ->emptyStateDescription('Siswa muncul di sini setelah pembayaran pertama diterima atau pembeli Scalev diimpor.')
            ->recordActions([
                Action::make('chat')
                    ->label('Chat WA')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('gray')
                    ->url(fn (Student $record) => 'https://wa.me/'.$record->whatsapp, shouldOpenInNewTab: true)
                    ->visible(fn (Student $record) => filled($record->whatsapp)),
            ]);
    }
}
