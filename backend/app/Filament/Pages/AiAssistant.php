<?php

namespace App\Filament\Pages;

use App\Jobs\AnswerAdminQuestion;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\Ai\AdminAssistant;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Chat with AI Pitcar Academy about the business. The answer is written on
 * the queue worker; the page polls until it lands.
 */
class AiAssistant extends Page
{
    protected static ?string $title = 'AI Pitcar Academy';

    protected static ?string $slug = 'ai-pitcar-academy';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Kelas online';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.ai-assistant';

    public string $question = '';

    public ?int $conversationId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->seesAllLeads() ?? false;
    }

    public function mount(): void
    {
        $this->conversationId = AdminAssistant::conversationFor(auth()->id())->id;
    }

    public function getSubheading(): ?string
    {
        return 'Tanya soal penjualan, iklan, siswa, diskusi, dan masukan. AI hanya membaca data; perubahan tetap dilakukan di menu admin.';
    }

    public function ask(): void
    {
        $this->validate(['question' => ['required', 'string', 'min:3', 'max:4000']], [
            'question.required' => 'Tulis pertanyaannya dulu.',
            'question.min' => 'Pertanyaannya terlalu pendek.',
        ]);

        if ($this->pending()) {
            return;
        }

        $conversation = $this->conversation();
        $conversation->messages()->create(['role' => 'user', 'content' => trim($this->question)]);
        $answer = $conversation->messages()->create(['role' => 'assistant', 'status' => AiMessage::PENDING]);
        $conversation->update(['title' => $conversation->title ?? Str::limit(trim($this->question), 80)]);

        AnswerAdminQuestion::dispatch($answer->id);
        $this->question = '';
    }

    public function suggest(int $index): void
    {
        $this->question = $this->suggestions()[$index] ?? '';
        $this->ask();
    }

    public function newConversation(): void
    {
        if ($this->pending()) {
            return;
        }

        $this->conversationId = AiConversation::create(['user_id' => auth()->id()])->id;
    }

    /** A failed answer is removed with its question, so it can be asked again. */
    public function retry(int $id): void
    {
        $failed = $this->conversation()->messages()->whereKey($id)->where('status', AiMessage::FAILED)->first();

        if ($failed) {
            $question = $this->conversation()->messages()->where('id', '<', $failed->id)->where('role', 'user')->latest('id')->first();
            $this->question = (string) $question?->content;
            $question?->delete();
            $failed->delete();
        }
    }

    public function enabled(): bool
    {
        return app(AdminAssistant::class)->enabled();
    }

    public function pending(): bool
    {
        return $this->conversation()->messages()->where('status', AiMessage::PENDING)->exists();
    }

    /** @return Collection<int, AiMessage> */
    public function messages(): Collection
    {
        return $this->conversation()->messages()->get();
    }

    /** @return list<string> */
    public function suggestions(): array
    {
        return [
            'Bagaimana penjualan dan biaya iklan 7 hari terakhir dibanding 7 hari sebelumnya?',
            'Siapa saja yang pesan tapi belum bayar minggu ini?',
            'Pertanyaan diskusi mana yang belum dijawab? Buatkan draf balasannya.',
            'Siswa kelas AC mana yang belum mulai menonton?',
        ];
    }

    public static function html(?string $text): string
    {
        return Str::markdown((string) $text, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }

    private function conversation(): AiConversation
    {
        return AiConversation::where('user_id', auth()->id())->findOrFail($this->conversationId);
    }
}
