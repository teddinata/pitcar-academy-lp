<?php

namespace App\Filament\Resources\Courses\RelationManagers;

use App\Enums\EnrollmentSource;
use App\Models\CourseReview;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Who has this class, how far they got, and what they thought of it. Read only:
 * access is granted by payment or from the order list, never edited here.
 */
class StudentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    protected static ?string $title = 'Siswa';

    protected static ?string $modelLabel = 'siswa';

    // Names, emails and numbers: managers and admins only, like the order list.
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->seesAllLeads() ?? false;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $course = $this->getOwnerRecord();
        $lessonIds = $course->lessons()->pluck('lessons.id');
        $lessonCount = max(1, $lessonIds->count());
        $progress = fn () => LessonProgress::query()->whereColumn('lesson_progress.student_id', 'enrollments.student_id')->whereIn('lesson_id', $lessonIds);

        return $table
            ->heading('Siswa')
            ->description($lessonIds->isEmpty() ? 'Kelas ini belum punya video, jadi progres belum bisa dihitung.' : null)
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->whereNull('revoked_at')
                ->with('student')
                ->addSelect([
                    'watched_total' => $progress()->selectRaw('coalesce(sum(watched_percent), 0)'),
                    'videos_done' => $progress()->whereNotNull('completed_at')->selectRaw('count(*)'),
                    'last_seen_at' => $progress()->selectRaw('max(lesson_progress.updated_at)'),
                    'rating' => CourseReview::query()
                        ->whereColumn('course_reviews.student_id', 'enrollments.student_id')
                        ->whereColumn('course_reviews.course_id', 'enrollments.course_id')
                        ->select('rating'),
                ]))
            ->defaultSort('granted_at', 'desc')
            ->columns([
                TextColumn::make('student.name')
                    ->label('Siswa')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Enrollment $record) => $record->student->email),

                TextColumn::make('student.whatsapp')
                    ->label('WhatsApp')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('watched_total')
                    ->label('Progres')
                    ->state(fn (Enrollment $record) => (int) round(((int) $record->watched_total) / $lessonCount).'%')
                    ->description(fn (Enrollment $record) => ((int) $record->videos_done).' dari '.$lessonIds->count().' video selesai')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('watched_total', $direction)),

                TextColumn::make('last_seen_at')
                    ->label('Terakhir belajar')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Belum mulai')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('last_seen_at', $direction)),

                TextColumn::make('rating')
                    ->label('Nilai')
                    ->state(fn (Enrollment $record) => $record->rating ? $record->rating.' / 5' : null)
                    ->placeholder('Belum menilai')
                    ->color(fn (Enrollment $record) => $record->rating && $record->rating <= 2 ? 'danger' : null),

                TextColumn::make('source')
                    ->label('Sumber')
                    ->badge()
                    ->formatStateUsing(fn (EnrollmentSource $state) => match ($state) {
                        EnrollmentSource::Doku => 'DOKU',
                        EnrollmentSource::Scalev => 'Scalev',
                        EnrollmentSource::Manual => 'Manual',
                    })
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('granted_at')
                    ->label('Akses sejak')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                // The ones who paid and never pressed play are the first to call.
                Filter::make('not_started')
                    ->label('Belum mulai belajar')
                    ->query(fn (Builder $query) => $query->whereNotExists($progress()->toBase()->selectRaw('1'))),

                SelectFilter::make('source')
                    ->label('Sumber')
                    ->options([
                        EnrollmentSource::Doku->value => 'DOKU',
                        EnrollmentSource::Scalev->value => 'Scalev',
                        EnrollmentSource::Manual->value => 'Manual',
                    ]),
            ])
            ->recordActions([
                Action::make('chat')
                    ->label('Chat WA')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('gray')
                    ->url(fn (Enrollment $record) => 'https://wa.me/'.$record->student->whatsapp, shouldOpenInNewTab: true)
                    ->visible(fn (Enrollment $record) => filled($record->student->whatsapp)),
            ]);
    }
}
