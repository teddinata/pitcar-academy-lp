<?php

namespace App\Filament\Resources\StudentRequests\Tables;

use App\Enums\RequestKind;
use App\Enums\RequestStatus;
use App\Models\StudentRequest;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StudentRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Dikirim')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('kind')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (RequestKind $state) => $state->label())
                    ->color(fn (RequestKind $state) => $state === RequestKind::Problem ? 'danger' : 'gray'),

                TextColumn::make('student.name')
                    ->label('Siswa')
                    ->searchable()
                    ->description(fn (StudentRequest $record) => $record->student->email),

                TextColumn::make('course.title')
                    ->label('Kelas')
                    ->placeholder('Umum')
                    ->limit(32)
                    ->toggleable(),

                TextColumn::make('body')
                    ->label('Pesan')
                    ->searchable()
                    ->wrap()
                    ->limit(220),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (RequestStatus $state) => $state->label())
                    ->color(fn (RequestStatus $state) => match ($state) {
                        RequestStatus::New => 'warning',
                        RequestStatus::Reviewing => 'info',
                        RequestStatus::Done => 'success',
                    }),

                TextColumn::make('reply')
                    ->label('Balasan')
                    ->placeholder('Belum dibalas')
                    ->wrap()
                    ->limit(120)
                    ->description(fn (StudentRequest $record) => $record->replied_at
                        ? trim(($record->repliedBy?->name ?? '').', '.$record->replied_at->timezone(config('app.display_timezone'))->format('d M H:i'), ', ')
                        : null)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(RequestStatus::cases())->mapWithKeys(fn (RequestStatus $s) => [$s->value => $s->label()])),

                SelectFilter::make('kind')
                    ->label('Jenis')
                    ->options(collect(RequestKind::cases())->mapWithKeys(fn (RequestKind $k) => [$k->value => $k->label()])),

                SelectFilter::make('course_id')
                    ->label('Kelas')
                    ->relationship('course', 'title'),
            ])
            ->recordActions([self::respond()]);
    }

    private static function respond(): Action
    {
        return Action::make('respond')
            ->label('Tanggapi')
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->button()
            ->modalHeading(fn (StudentRequest $record) => $record->kind->label().' dari '.$record->student->name)
            ->modalDescription(fn (StudentRequest $record) => $record->body)
            ->modalSubmitActionLabel('Simpan')
            ->fillForm(fn (StudentRequest $record) => [
                // Opening a new message to answer it means someone is on it.
                'status' => $record->status === RequestStatus::New ? RequestStatus::Reviewing->value : $record->status->value,
                'reply' => $record->reply,
            ])
            ->schema([
                Select::make('status')
                    ->label('Status')
                    ->options(collect(RequestStatus::cases())->mapWithKeys(fn (RequestStatus $s) => [$s->value => $s->label()]))
                    ->required()
                    ->native(false),
                Textarea::make('reply')
                    ->label('Balasan untuk siswa')
                    ->helperText('Terlihat oleh siswa di halaman Masukan pada area belajar. Kosongkan kalau belum perlu dibalas.')
                    ->rows(5)
                    ->maxLength(2000),
            ])
            ->action(function (StudentRequest $record, array $data) {
                $reply = trim((string) ($data['reply'] ?? ''));
                $reply = $reply === '' ? null : $reply;
                $changed = $reply !== $record->reply;

                $record->update([
                    'status' => $data['status'],
                    'reply' => $reply,
                    'replied_at' => $changed ? ($reply ? now() : null) : $record->replied_at,
                    'replied_by' => $changed ? ($reply ? auth()->id() : null) : $record->replied_by,
                ]);

                Notification::make()->success()->title($changed && $reply ? 'Balasan tersimpan dan terlihat oleh siswa' : 'Status tersimpan')->send();
            });
    }
}
