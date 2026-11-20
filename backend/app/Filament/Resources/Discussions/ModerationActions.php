<?php

namespace App\Filament\Resources\Discussions;

use App\Models\DiscussionPost;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

/** The three things the team does to a discussion message: answer, remove, clear reports. */
class ModerationActions
{
    /** @param  Closure(): DiscussionPost|null  $target  for header actions that act on the page's thread */
    public static function reply(?Closure $target = null): Action
    {
        return Action::make('teamReply')
            ->label('Balas')
            ->icon('heroicon-o-chat-bubble-left')
            ->color('primary')
            ->visible(fn (?DiscussionPost $record) => ! self::post($record, $target)?->isRemoved())
            ->modalHeading('Balas sebagai tim')
            ->modalDescription(fn (?DiscussionPost $record) => 'Membalas: "'.str(self::post($record, $target)?->body)->limit(160).'". Siswa melihat namamu dengan tanda Tim.')
            ->modalSubmitActionLabel('Kirim balasan')
            ->schema([
                Textarea::make('body')->label('Balasan')->required()->rows(6)->maxLength(2000),
            ])
            ->action(function (?DiscussionPost $record, array $data) use ($target) {
                self::post($record, $target)->replyAsTeam(auth()->user(), $data['body']);
                Notification::make()->success()->title('Balasan terkirim')->send();
            });
    }

    public static function remove(?Closure $target = null): Action
    {
        return Action::make('remove')
            ->label('Hapus')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->visible(fn (?DiscussionPost $record) => ! self::post($record, $target)?->isRemoved())
            ->modalHeading(fn (?DiscussionPost $record) => self::post($record, $target)?->isThread() ? 'Hapus pertanyaan ini?' : 'Hapus balasan ini?')
            ->modalDescription(fn (?DiscussionPost $record) => self::post($record, $target)?->isThread()
                ? 'Pertanyaan dan semua balasannya hilang dari halaman kelas. Isinya tetap tersimpan di sini sebagai catatan.'
                : 'Balasan ini hilang dari halaman kelas. Isinya tetap tersimpan di sini sebagai catatan.')
            ->modalSubmitActionLabel('Hapus')
            ->schema([
                Select::make('reason')->label('Alasan')->options(DiscussionPost::REASONS)->required()->native(false),
            ])
            ->action(function (?DiscussionPost $record, array $data) use ($target) {
                self::post($record, $target)->remove(auth()->user(), $data['reason']);
                Notification::make()->success()->title('Pesan dihapus dari halaman kelas')->send();
            });
    }

    public static function dismissReports(?Closure $target = null): Action
    {
        return Action::make('dismissReports')
            ->label('Abaikan laporan')
            ->icon('heroicon-o-check')
            ->color('gray')
            ->visible(fn (?DiscussionPost $record) => ($post = self::post($record, $target)) && ! $post->isRemoved() && $post->reports()->whereNull('resolved_at')->exists())
            ->requiresConfirmation()
            ->modalHeading('Pesan ini tidak melanggar?')
            ->modalDescription('Laporan ditandai sudah diperiksa dan pesan tetap tampil.')
            ->modalSubmitActionLabel('Ya, abaikan laporan')
            ->action(function (?DiscussionPost $record) use ($target) {
                self::post($record, $target)->reports()->whereNull('resolved_at')->update(['resolved_at' => now()]);
                Notification::make()->success()->title('Laporan ditandai sudah diperiksa')->send();
            });
    }

    private static function post(?DiscussionPost $record, ?Closure $target): ?DiscussionPost
    {
        return $target ? $target() : $record;
    }
}
