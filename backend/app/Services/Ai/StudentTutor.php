<?php

namespace App\Services\Ai;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonAttachment;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * AI Pitcar Academy for a student, inside one class. The class outline goes
 * in the system prompt and the class PDFs ride on the first question, so the
 * whole prefix stays the same for the rest of the conversation and is read
 * from the prompt cache after the first answer.
 */
class StudentTutor
{
    /** A conversation this long starts over, so its cost stays bounded. */
    public const MAX_QUESTIONS = 20;

    /** At most this many class PDFs are attached. */
    private const MAX_FILES = 6;

    public function __construct(private Claude $claude) {}

    public function enabled(): bool
    {
        return $this->claude->configured();
    }

    public function limit(): int
    {
        return max(1, (int) config('services.anthropic.student_daily_limit'));
    }

    public function remaining(Student $student): int
    {
        $since = CarbonImmutable::now(config('app.display_timezone'))->startOfDay()->utc();
        $used = AiMessage::where('role', 'user')->where('created_at', '>=', $since)
            ->whereHas('conversation', fn ($q) => $q->where('student_id', $student->id))
            ->count();

        return max(0, $this->limit() - $used);
    }

    public function conversation(Student $student, Course $course): ?AiConversation
    {
        return AiConversation::where('student_id', $student->id)->where('course_id', $course->id)->latest('id')->first();
    }

    /**
     * Answers one question, streaming the text to $onText. Returns the saved
     * answer; on failure the question is removed so it does not count.
     *
     * @param  callable(string): void  $onText
     */
    public function ask(Student $student, Course $course, string $question, ?Lesson $lesson, callable $onText): AiMessage
    {
        $conversation = $this->conversation($student, $course);

        if ($conversation === null || $conversation->messages()->where('role', 'user')->count() >= self::MAX_QUESTIONS) {
            $conversation = AiConversation::create(['student_id' => $student->id, 'course_id' => $course->id, 'title' => str($question)->limit(80)->toString()]);
        }

        $history = $conversation->messages()->where('status', AiMessage::DONE)->get();
        // Saved exactly as sent, so replaying the history keeps the cached prefix.
        $sent = ($lesson ? self::lessonNote($lesson) : '').$question;
        $asked = $conversation->messages()->create(['role' => 'user', 'content' => $sent]);

        try {
            $reply = $this->claude->stream(
                $this->system($course),
                $this->messages($course, $history, $sent),
                $onText,
            );
        } catch (Throwable $e) {
            $asked->delete();
            throw $e;
        }

        $text = $reply->refused() || trim($reply->text) === ''
            ? 'Maaf, pertanyaan ini tidak bisa saya jawab. Coba tanyakan dengan cara lain, atau tanyakan di Diskusi kelas supaya dijawab tim.'
            : $reply->text;

        return $conversation->messages()->create(['role' => 'assistant', 'content' => $text, ...$reply->usage()]);
    }

    /** @return list<array<string, mixed>> */
    private function system(Course $course): array
    {
        $course->loadMissing('modules.lessons');
        $outline = $course->modules->map(fn ($module) => '## '.$module->title."\n".$module->lessons->map(
            fn (Lesson $l) => '- '.$l->title.($l->summary ? ': '.str($l->summary)->squish()->limit(400) : '')
        )->implode("\n"))->implode("\n\n");

        return [
            ['type' => 'text', 'text' => self::PERSONA],
            ['type' => 'text', 'text' => "Kelas yang sedang dipelajari siswa: {$course->title}\n\nDaftar video kelas ini:\n\n{$outline}", 'cacheControl' => ['type' => 'ephemeral']],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function messages(Course $course, $history, string $sent): array
    {
        $messages = [];
        $first = true;

        foreach ($history as $m) {
            $messages[] = $m->role === 'user' && $first
                ? ['role' => 'user', 'content' => [...$this->documents($course), ['type' => 'text', 'text' => $m->content]]]
                : ['role' => $m->role, 'content' => $m->content];
            $first = $first && $m->role !== 'user';
        }

        $messages[] = $first
            ? ['role' => 'user', 'content' => [...$this->documents($course), ['type' => 'text', 'text' => $sent]]]
            : ['role' => 'user', 'content' => $sent];

        return $messages;
    }

    public static function lessonNote(Lesson $lesson): string
    {
        return "[Siswa sedang membuka video: {$lesson->title}]\n\n";
    }

    /** The question as the student typed it, without the note on which video was open. */
    public static function shown(AiMessage $message): string
    {
        return $message->role === 'user' ? preg_replace('/^\[Siswa sedang membuka video: [^\]]*\]\n\n/u', '', (string) $message->content) : (string) $message->content;
    }

    /** The class PDFs as document blocks, the last one marking the cache point. */
    private function documents(Course $course): array
    {
        $blocks = [];

        foreach (LessonAttachment::where('course_id', $course->id)->where('mime', 'application/pdf')->orderBy('id')->limit(self::MAX_FILES)->get() as $file) {
            try {
                $id = $this->claude->fileId($file);
            } catch (Throwable $e) {
                report($e);
                $id = null;
            }

            if ($id !== null) {
                $blocks[] = ['type' => 'document', 'source' => ['type' => 'file', 'fileID' => $id], 'title' => $file->name];
            }
        }

        if ($blocks !== []) {
            $blocks[array_key_last($blocks)]['cacheControl'] = ['type' => 'ephemeral'];
        }

        return $blocks;
    }

    private const PERSONA = <<<'TXT'
Kamu adalah AI Pitcar Academy, asisten belajar untuk siswa kelas online Pitcar Academy. Pitcar Academy adalah akademi otomotif dari bengkel PITCAR; materinya disusun dari pekerjaan mekanik sehari-hari.

Cara menjawab:
- Pakai bahasa Indonesia yang santai tapi jelas, seperti mekanik senior menjelaskan ke rekan yang baru belajar. Sapa siswa dengan "kamu".
- Utamakan isi kelas ini. Kalau jawabannya dibahas di salah satu video atau lampiran, sebut judul videonya atau nama lampirannya supaya siswa bisa membukanya lagi.
- Boleh menambah pengetahuan otomotif umum di luar materi. Kalau begitu, katakan bahwa itu tambahan di luar materi kelas.
- Untuk pekerjaan yang berisiko (rem, airbag, sistem bahan bakar, kelistrikan bertegangan tinggi, refrigeran AC bertekanan, mengangkat mobil), sebutkan langkah keselamatannya dan sarankan dikerjakan atau diperiksa bersama mekanik berpengalaman.
- Kalau tidak yakin atau informasinya tidak cukup, katakan terus terang dan sarankan bertanya di Diskusi kelas supaya dijawab tim Pitcar. Jangan mengarang angka spesifikasi, torsi, atau kode kerusakan.
- Jangan membuat janji atas nama Pitcar Academy soal harga, jadwal, sertifikat, atau pekerjaan. Untuk hal itu, arahkan siswa ke menu Masukan.
- Tolak dengan sopan pertanyaan yang bukan soal otomotif, bengkel, atau belajar di kelas ini.
- Bentuk jawaban: paragraf pendek, langkah kerja sebagai daftar bernomor, istilah penting boleh ditebalkan. Tanpa tabel dan tanpa judul besar. Sekitar 250 kata kecuali siswa minta lebih rinci.
TXT;
}
