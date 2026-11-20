<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Course;
use App\Models\Student;
use App\Services\Ai\StudentTutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * AI Pitcar Academy inside a class. The answer streams as server-sent events
 * (delta, then done or error), so the student reads it as it is written and
 * the connection never sits idle long enough for a proxy to cut it.
 */
class LearnAiController extends Controller
{
    public function show(Request $request, string $slug, StudentTutor $tutor): JsonResponse
    {
        [$student, $course] = $this->access($request, $slug);
        $conversation = $tutor->conversation($student, $course);

        return response()->json([
            'enabled' => $tutor->enabled(),
            'limit' => $tutor->limit(),
            'remaining' => $tutor->remaining($student),
            'messages' => $conversation ? $this->messages($conversation) : [],
        ]);
    }

    public function ask(Request $request, string $slug, StudentTutor $tutor): StreamedResponse|JsonResponse
    {
        [$student, $course] = $this->access($request, $slug);
        $data = $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:1500'],
            'lesson_id' => ['nullable', 'integer'],
        ], [
            'question.required' => 'Tulis pertanyaanmu dulu.',
            'question.min' => 'Pertanyaannya terlalu pendek.',
            'question.max' => 'Pertanyaannya terlalu panjang, maksimal 1.500 karakter.',
        ]);

        if (! $tutor->enabled()) {
            return response()->json(['message' => 'AI Pitcar Academy belum aktif. Tanyakan di Diskusi kelas dulu, ya.'], 503);
        }

        if ($tutor->remaining($student) <= 0) {
            return response()->json(['message' => 'Jatah '.$tutor->limit().' pertanyaan hari ini sudah habis. Bisa bertanya lagi besok, atau tanyakan di Diskusi kelas.'], 429);
        }

        $lesson = isset($data['lesson_id']) ? $course->lessons()->whereKey($data['lesson_id'])->first() : null;

        return response()->stream(function () use ($tutor, $student, $course, $data, $lesson) {
            @set_time_limit(180);
            $send = function (string $event, array $payload) {
                echo "event: {$event}\ndata: ".json_encode($payload, JSON_UNESCAPED_UNICODE)."\n\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };

            try {
                $answer = $tutor->ask($student, $course, trim($data['question']), $lesson, fn (string $text) => $send('delta', ['text' => $text]));
                $send('done', ['text' => $answer->content, 'remaining' => $tutor->remaining($student)]);
            } catch (Throwable $e) {
                report($e);
                $send('error', ['message' => 'AI Pitcar Academy sedang tidak bisa menjawab. Coba lagi sebentar lagi.']);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /** Starts a new conversation; the old one stays for the record. */
    public function reset(Request $request, string $slug): JsonResponse
    {
        [$student, $course] = $this->access($request, $slug);
        AiConversation::create(['student_id' => $student->id, 'course_id' => $course->id]);

        return response()->json(['ok' => true]);
    }

    private function messages(AiConversation $conversation): array
    {
        return $conversation->messages()->where('status', AiMessage::DONE)->get()
            ->map(fn (AiMessage $m) => ['role' => $m->role, 'text' => StudentTutor::shown($m)])
            ->all();
    }

    /** @return array{0: Student, 1: Course} */
    private function access(Request $request, string $slug): array
    {
        $student = $request->attributes->get('student');
        $course = Course::where('slug', $slug)->firstOrFail();
        abort_unless($student->hasAccessTo($course), 403, 'Kamu belum punya akses ke kelas ini.');

        return [$student, $course];
    }
}
