<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonAttachment;
use App\Models\LessonProgress;
use App\Models\Student;
use App\Services\Lms\LoginLink;
use App\Services\Lms\PasswordIssuer;
use App\Services\Lms\ProgressRecorder;
use App\Services\Lms\StudentSessions;
use App\Support\VideoLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LearnController extends Controller
{
    /** The answer is the same whether or not the address has a class, so the form cannot be used to look people up. */
    public function requestLink(Request $request, LoginLink $links): JsonResponse
    {
        $email = $request->validate(['email' => ['required', 'email:rfc', 'max:190']], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email belum benar.',
        ])['email'];

        $student = Student::where('email', mb_strtolower(trim($email)))->first();

        if ($student && $student->enrollments()->whereNull('revoked_at')->exists()) {
            $links->send($student);
        }

        return response()->json(['message' => 'OK']);
    }

    public function startSession(Request $request, LoginLink $links, StudentSessions $sessions): JsonResponse
    {
        $student = $links->consume((string) $request->input('token'));

        if ($student === null) {
            return response()->json(['message' => 'Tautan masuk ini sudah dipakai atau kedaluwarsa. Minta tautan baru.'], 422);
        }

        return response()->json([
            'session' => $sessions->start($student),
            'student' => ['name' => $student->name, 'email' => $student->email],
        ]);
    }

    public function login(Request $request, PasswordIssuer $passwords, StudentSessions $sessions): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:190'], 'password' => ['required', 'string', 'max:64']], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email belum benar.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        $email = mb_strtolower(trim($data['email']));
        $key = 'learn-login:'.sha1($email.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['message' => 'Terlalu banyak percobaan. Tunggu beberapa menit, atau masuk dengan tautan lewat email.'], 429);
        }

        $student = Student::where('email', $email)->first();

        // One message for a wrong address and a wrong password, so neither can be probed.
        if ($student === null || ! $passwords->matches($student, $data['password'])) {
            RateLimiter::hit($key, 600);

            return response()->json(['message' => 'Email atau kata sandi salah.'], 422);
        }

        RateLimiter::clear($key);
        $student->update(['last_login_at' => now()]);

        return response()->json([
            'session' => $sessions->start($student),
            'student' => ['name' => $student->name, 'email' => $student->email],
        ]);
    }

    /**
     * The student replaces the system-made password with one of their own.
     * Other signed-in devices are signed out, in case the change was made
     * because someone else knew the old one.
     */
    public function changePassword(Request $request, PasswordIssuer $passwords, StudentSessions $sessions): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'min:8', 'max:72', 'confirmed']], [
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.max' => 'Kata sandi maksimal 72 karakter.',
            'password.confirmed' => 'Ulangi kata sandi dengan isi yang sama.',
        ]);

        $student = $this->student($request);
        $passwords->set($student, $data['password']);
        $sessions->endOthers($student, $request->bearerToken());

        return response()->json(['message' => 'OK']);
    }

    public function endSession(Request $request, StudentSessions $sessions): JsonResponse
    {
        $sessions->end($request->bearerToken());

        return response()->json(['message' => 'OK']);
    }

    public function courses(Request $request, ProgressRecorder $recorder): JsonResponse
    {
        $student = $this->student($request);

        $courses = Course::whereIn('id', $student->enrollments()->whereNull('revoked_at')->pluck('course_id'))
            ->with('modules.lessons')
            ->get()
            ->map(function (Course $course) use ($student, $recorder) {
                $lessons = $course->modules->flatMap->lessons;
                $progress = LessonProgress::where('student_id', $student->id)->whereIn('lesson_id', $lessons->pluck('id'))->get()->keyBy('lesson_id');
                $next = $lessons->first(fn (Lesson $l) => $progress->get($l->id)?->completed_at === null);

                return [
                    'slug' => $course->slug,
                    'title' => $course->title,
                    'percent' => $recorder->coursePercent($student, $course),
                    'lessons' => $lessons->count(),
                    'completed' => $progress->whereNotNull('completed_at')->count(),
                    'next' => $next ? ['id' => $next->id, 'title' => $next->title, 'position' => $progress->get($next->id)?->last_position_seconds ?? 0] : null,
                ];
            });

        return response()->json([
            'student' => ['name' => $student->name, 'email' => $student->email, 'whatsapp' => $student->whatsapp, 'password' => $student->passwordKind()],
            'courses' => $courses->values(),
        ]);
    }

    public function course(Request $request, string $slug, ProgressRecorder $recorder): JsonResponse
    {
        $student = $this->student($request);
        $course = Course::where('slug', $slug)->with(['modules.lessons', 'attachments'])->firstOrFail();

        if (! $student->hasAccessTo($course)) {
            return response()->json(['message' => 'Kamu belum punya akses ke kelas ini.'], 403);
        }

        $lessonIds = $course->modules->flatMap->lessons->pluck('id');
        $progress = LessonProgress::where('student_id', $student->id)->whereIn('lesson_id', $lessonIds)->get()->keyBy('lesson_id');
        $file = fn (LessonAttachment $a) => ['id' => $a->id, 'name' => $a->name, 'mime' => $a->mime, 'size' => $a->size];

        return response()->json([
            'slug' => $course->slug,
            'title' => $course->title,
            'percent' => $recorder->coursePercent($student, $course),
            'complete_at' => (int) config('lms.complete_at_percent'),
            'review' => LearnFeedbackController::review($course->reviews()->where('student_id', $student->id)->first()),
            'files' => $course->attachments->whereNull('lesson_id')->map($file)->values(),
            'modules' => $course->modules->map(fn ($module) => [
                'title' => $module->title,
                'lessons' => $module->lessons->map(fn (Lesson $lesson) => [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'summary' => $lesson->summary,
                    'video' => VideoLink::parse($lesson->youtube_url)['id'] ?? null,
                    'provider' => VideoLink::parse($lesson->youtube_url)['provider'] ?? null,
                    'duration' => $lesson->duration_seconds,
                    'percent' => $progress->get($lesson->id)?->watched_percent ?? 0,
                    'position' => $progress->get($lesson->id)?->last_position_seconds ?? 0,
                    'done' => $progress->get($lesson->id)?->completed_at !== null,
                    'files' => $course->attachments->where('lesson_id', $lesson->id)->map($file)->values(),
                ]),
            ]),
        ]);
    }

    public function progress(Request $request, Lesson $lesson, ProgressRecorder $recorder): JsonResponse
    {
        $student = $this->student($request);

        if (! $student->canWatch($lesson)) {
            return response()->json(['message' => 'Kamu belum punya akses ke kelas ini.'], 403);
        }

        $data = $request->validate([
            'percent' => ['required', 'integer', 'min:0', 'max:100'],
            'position' => ['required', 'integer', 'min:0'],
        ]);

        $row = $recorder->record($student, $lesson, $data['percent'], $data['position']);

        return response()->json([
            'percent' => $row->watched_percent,
            'done' => $row->completed_at !== null,
            'course_percent' => $recorder->coursePercent($student, $lesson->course()),
        ]);
    }

    public function download(Request $request, LessonAttachment $attachment): StreamedResponse|JsonResponse
    {
        if (! $this->student($request)->hasAccessTo($attachment->course)) {
            return response()->json(['message' => 'Kamu belum punya akses ke kelas ini.'], 403);
        }

        if (! Storage::exists($attachment->path)) {
            return response()->json(['message' => 'Berkas ini tidak ditemukan. Hubungi kami supaya bisa diunggah ulang.'], 404);
        }

        return Storage::download($attachment->path, $attachment->name);
    }

    private function student(Request $request): Student
    {
        return $request->attributes->get('student');
    }

    /** The YouTube id of a link, or null for Drive and anything else. */
    public static function youtubeId(?string $url): ?string
    {
        $video = VideoLink::parse($url);

        return $video && $video['provider'] === VideoLink::YOUTUBE ? $video['id'] : null;
    }
}
