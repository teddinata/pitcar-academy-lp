<?php

namespace App\Filament\Resources\Courses\RelationManagers;

use App\Models\LessonAttachment;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

/**
 * Files students download from the class. Stored on the private default disk:
 * only the learn API serves them, and only to students of this class.
 */
class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    protected static ?string $title = 'Lampiran';

    protected static ?string $modelLabel = 'lampiran';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('path')
                ->label('Berkas')
                ->disk(config('filesystems.default'))
                ->directory('lms/attachments')
                ->visibility('private')
                ->maxSize(20480)
                ->acceptedFileTypes([
                    'application/pdf',
                    'application/zip',
                    'image/jpeg',
                    'image/png',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])
                ->required()
                ->columnSpanFull()
                ->helperText('PDF, ZIP, gambar, Word, atau Excel. Maksimal 20 MB.'),

            TextInput::make('name')
                ->label('Nama berkas untuk siswa')
                ->required()
                ->maxLength(150)
                ->placeholder('Modul Pendamping AC Mobil.pdf'),

            Select::make('lesson_id')
                ->label('Tempel di video')
                ->options(fn () => $this->getOwnerRecord()->lessons()->orderBy('course_modules.sort')->orderBy('lessons.sort')->pluck('lessons.title', 'lessons.id'))
                ->placeholder('Seluruh kelas')
                ->helperText('Kosongkan kalau berkas ini untuk seluruh kelas.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Berkas')->weight('bold')->wrap(),
                TextColumn::make('lesson.title')->label('Ditempel di')->placeholder('Seluruh kelas')->wrap(),
                TextColumn::make('size')->label('Ukuran')->formatStateUsing(fn ($state) => $state ? Number::fileSize($state) : '—'),
                TextColumn::make('created_at')->label('Diunggah')->dateTime('d M Y'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Unggah lampiran')
                    ->mutateDataUsing(fn (array $data) => self::withFileFacts($data)),
            ])
            ->recordActions([
                EditAction::make()->mutateDataUsing(fn (array $data) => self::withFileFacts($data)),
                DeleteAction::make()
                    ->after(fn (LessonAttachment $record) => Storage::disk(config('filesystems.default'))->delete($record->path)),
            ]);
    }

    /** Type and size read from the stored file, for the student's download list. */
    private static function withFileFacts(array $data): array
    {
        $disk = Storage::disk(config('filesystems.default'));

        if (filled($data['path'] ?? null) && $disk->exists($data['path'])) {
            $data['mime'] = $disk->mimeType($data['path']) ?: null;
            $data['size'] = $disk->size($data['path']);
        }

        return $data;
    }
}
