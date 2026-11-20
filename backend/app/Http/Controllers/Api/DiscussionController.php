<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\DiscussionPost;
use App\Models\DiscussionReport;
use App\Models\ScalevPurchase;
use App\Models\Student;
use App\Services\Lms\TeamAlerts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class discussions for students who own the class. Threads come newest
 * activity first, ten at a time; a thread's replies load when it is opened.
 */
class DiscussionController extends Controller
{
    private const PAGE = 10;

    public function index(Request $request, string $slug): JsonResponse
    {
        [$student, $course] = $this->access($request, $slug);
        $lesson = $request->integer('lesson') ?: null;

        $query = DiscussionPost::threads()->visible()
            ->where('course_id', $course->id)
            ->when($lesson, fn ($q) => $q->where('lesson_id', $lesson))
            ->with(['student', 'user', 'lesson'])
            ->withExists(['replies as answered' => fn ($q) => $q->whereNotNull('user_id')->whereNull('deleted_at')])
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id');

        $total = (clone $query)->count();
        $threads = $query->offset(max(0, $request->integer('offset')))->limit(self::PAGE + 1)->get();
        $mine = $this->reportedBy($student, $threads->pluck('id')->all());

        return response()->json([
            'total' => $total,
            'class_total' => $lesson ? DiscussionPost::threads()->visible()->where('course_id', $course->id)->count() : $total,
            'has_more' => $threads->count() > self::PAGE,
            'threads' => $threads->take(self::PAGE)->map(fn (DiscussionPost $p) => $this->post($p, $student, $mine))->values(),
        ]);
    }

    public function show(Request $request, DiscussionPost $post): JsonResponse
    {
        $thread = $this->thread($request, $post);
        $student = $this->student($request);
        $replies = $thread->replies()->visible()->with(['student', 'user', 'replyTo.student', 'replyTo.user'])->get();
        $mine = $this->reportedBy($student, [$thread->id, ...$replies->pluck('id')->all()]);

        return response()->json([
            'thread' => $this->post($thread->load(['student', 'user', 'lesson']), $student, $mine),
            'replies' => $replies->map(fn (DiscussionPost $p) => $this->post($p, $student, $mine))->values(),
        ]);
    }

    public function store(Request $request, string $slug, TeamAlerts $alerts): JsonResponse
    {
        [$student, $course] = $this->access($request, $slug);
        $data = $request->validate([
            'body' => ['required', 'string', 'min:3', 'max:2000'],
            'lesson_id' => ['nullable', 'integer'],
        ], $this->messages());

        $lessonId = isset($data['lesson_id']) && $course->lessons()->whereKey($data['lesson_id'])->exists() ? (int) $data['lesson_id'] : null;

        $post = DiscussionPost::create([
            'course_id' => $course->id,
            'lesson_id' => $lessonId,
            'student_id' => $student->id,
            'body' => trim($data['body']),
            'last_activity_at' => now(),
        ]);

        $alerts->newThread($post);

        return response()->json(['thread' => $this->post($post->load(['student', 'lesson']), $student, [])], 201);
    }

    public function reply(Request $request, DiscussionPost $post): JsonResponse
    {
        $thread = $this->thread($request, $post);
        $student = $this->student($request);
        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:2000'],
            'reply_to_id' => ['nullable', 'integer'],
        ], $this->messages());

        $replyTo = isset($data['reply_to_id'])
            ? $thread->replies()->visible()->whereKey($data['reply_to_id'])->value('id')
            : null;

        $reply = DiscussionPost::create([
            'course_id' => $thread->course_id,
            'lesson_id' => $thread->lesson_id,
            'thread_id' => $thread->id,
            'reply_to_id' => $replyTo,
            'student_id' => $student->id,
            'body' => trim($data['body']),
        ]);
        $thread->update(['last_activity_at' => now()]);
        $thread->recount();

        return response()->json(['reply' => $this->post($reply->load(['student', 'replyTo.student', 'replyTo.user']), $student, [])], 201);
    }

    /** A student takes down their own message. */
    public function destroy(Request $request, DiscussionPost $post): JsonResponse
    {
        $student = $this->student($request);

        if ($post->student_id !== $student->id || $post->isRemoved()) {
            return response()->json(['message' => 'Pesan ini tidak bisa kamu hapus.'], 403);
        }

        $post->remove(null, DiscussionPost::REASON_AUTHOR);

        return response()->json(['ok' => true]);
    }

    public function report(Request $request, DiscussionPost $post, TeamAlerts $alerts): JsonResponse
    {
        $student = $this->student($request);

        if (! $student->hasAccessTo($post->course) || $post->isRemoved() || $post->student_id === $student->id) {
            return response()->json(['message' => 'Pesan ini tidak bisa dilaporkan.'], 403);
        }

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:300']]);
        $report = DiscussionReport::firstOrCreate(
            ['post_id' => $post->id, 'student_id' => $student->id],
            ['reason' => filled($data['reason'] ?? null) ? trim($data['reason']) : null],
        );

        if ($report->wasRecentlyCreated) {
            $alerts->reported($post);
        }

        return response()->json(['ok' => true]);
    }

    /** @return array{0: Student, 1: Course} */
    private function access(Request $request, string $slug): array
    {
        $student = $this->student($request);
        $course = Course::where('slug', $slug)->firstOrFail();
        abort_unless($student->hasAccessTo($course), 403, 'Kamu belum punya akses ke kelas ini.');

        return [$student, $course];
    }

    private function thread(Request $request, DiscussionPost $post): DiscussionPost
    {
        $thread = $post->isThread() ? $post : $post->thread;
        abort_if($thread === null || $thread->isRemoved(), 404, 'Diskusi ini sudah dihapus.');
        abort_unless($this->student($request)->hasAccessTo($thread->course), 403, 'Kamu belum punya akses ke kelas ini.');

        return $thread;
    }

    /** @param  list<int>  $reported */
    private function post(DiscussionPost $p, Student $student, array $reported): array
    {
        $team = $p->user_id !== null;

        return array_filter([
            'id' => $p->id,
            'author' => $team ? strtok((string) $p->user?->name, ' ') ?: 'Tim' : ScalevPurchase::mask($p->student?->name ?? 'Siswa'),
            'team' => $team,
            'mine' => $p->student_id === $student->id,
            'body' => $p->body,
            'created_at' => $p->created_at?->toIso8601String(),
            'lesson' => $p->isThread() && $p->lesson ? ['id' => $p->lesson->id, 'title' => $p->lesson->title] : null,
            'reply_count' => $p->isThread() ? $p->reply_count : null,
            'answered' => $p->isThread() ? (bool) ($p->answered ?? false) : null,
            'reply_to' => $p->replyTo ? [
                'id' => $p->replyTo->id,
                'author' => $p->replyTo->user_id ? strtok((string) $p->replyTo->user?->name, ' ') ?: 'Tim' : ScalevPurchase::mask($p->replyTo->student?->name ?? 'Siswa'),
                'removed' => $p->replyTo->isRemoved(),
            ] : null,
            'reported' => in_array($p->id, $reported, true),
        ], fn ($v) => $v !== null);
    }

    /** @param  list<int>  $ids  @return list<int> */
    private function reportedBy(Student $student, array $ids): array
    {
        return DiscussionReport::where('student_id', $student->id)->whereIn('post_id', $ids)->pluck('post_id')->all();
    }

    private function student(Request $request): Student
    {
        return $request->attributes->get('student');
    }

    private function messages(): array
    {
        return [
            'body.required' => 'Tulis pesanmu dulu.',
            'body.min' => 'Pesannya terlalu pendek.',
            'body.max' => 'Pesannya terlalu panjang, maksimal 2.000 karakter.',
        ];
    }
}
