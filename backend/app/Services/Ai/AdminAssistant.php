<?php

namespace App\Services\Ai;

use App\Models\AiConversation;
use App\Models\AiMessage;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * AI Pitcar Academy for the team: answers questions about sales, students,
 * discussions and feedback by reading the data through BusinessTools. It
 * cannot change anything.
 */
class AdminAssistant
{
    public function __construct(private Claude $claude, private BusinessTools $tools) {}

    public function enabled(): bool
    {
        return $this->claude->configured();
    }

    /** Fills in a pending answer. Earlier turns go back as plain text. */
    public function answer(AiMessage $pending): void
    {
        $conversation = $pending->conversation;
        $history = $conversation->messages()->where('id', '<', $pending->id)->where('status', AiMessage::DONE)->get()
            ->map(fn (AiMessage $m) => ['role' => $m->role, 'content' => (string) $m->content])
            ->values()->all();

        try {
            $reply = $this->claude->runTools(
                $this->system(),
                $history,
                $this->tools->definitions(),
                fn (string $name, array $input) => $this->tools->run($name, $input),
            );

            $pending->update([
                'status' => AiMessage::DONE,
                'content' => $reply->refused() || trim($reply->text) === '' ? 'Maaf, pertanyaan ini tidak bisa saya jawab.' : $reply->text,
                ...$reply->usage(),
            ]);
        } catch (Throwable $e) {
            report($e);
            $pending->update(['status' => AiMessage::FAILED, 'error' => str($e->getMessage())->limit(500)->toString()]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function system(): array
    {
        $today = CarbonImmutable::now(config('app.display_timezone'))->translatedFormat('l, d F Y');

        return [
            ['type' => 'text', 'text' => self::PERSONA, 'cacheControl' => ['type' => 'ephemeral']],
            ['type' => 'text', 'text' => "Hari ini {$today} (WIB)."],
        ];
    }

    public static function conversationFor(int $userId): AiConversation
    {
        return AiConversation::where('user_id', $userId)->latest('id')->first()
            ?? AiConversation::create(['user_id' => $userId]);
    }

    private const PERSONA = <<<'TXT'
Kamu adalah AI Pitcar Academy untuk tim admin kelas online Pitcar Academy (akademi otomotif dari bengkel PITCAR). Kamu membantu tim membaca kondisi bisnis dan menyiapkan tindak lanjut.

Cara kerja:
- Untuk angka dan data, selalu pakai alat yang tersedia. Jangan menebak angka. Kalau alat tidak punya datanya, katakan begitu.
- Kamu hanya bisa membaca data. Kalau tim minta mengubah sesuatu (membalas diskusi, menghapus pesan, mengirim pesan ke siswa), siapkan drafnya dan jelaskan di menu admin mana mereka bisa melakukannya.
- Uang dalam rupiah dengan titik ribuan, misalnya Rp1.250.000. Waktu dalam WIB.
- Penjualan = pesanan DOKU lunas + pesanan Scalev selesai. Biaya iklan hanya dari Meta.
- Saat membuat draf balasan untuk siswa: bahasa Indonesia yang ramah dan jelas, tanpa janji yang belum pasti.
- Data siswa (email, WhatsApp) hanya untuk tindak lanjut internal; jangan sarankan membagikannya.
- Jawab singkat dan langsung: angka utama dulu, lalu penjelasan atau saran. Daftar boleh, tabel kecil boleh kalau membandingkan beberapa baris.
TXT;
}
