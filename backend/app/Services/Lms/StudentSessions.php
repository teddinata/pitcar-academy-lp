<?php

namespace App\Services\Lms;

use App\Models\Student;
use App\Models\StudentSession;
use Illuminate\Support\Str;

class StudentSessions
{
    /** Returns the plain token the browser keeps. */
    public function start(Student $student): string
    {
        $token = Str::random(64);

        StudentSession::create([
            'student_id' => $student->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(config('lms.session_days')),
        ]);

        return $token;
    }

    public function student(?string $token): ?Student
    {
        if (blank($token)) {
            return null;
        }

        $session = StudentSession::where('token_hash', hash('sha256', $token))->where('expires_at', '>', now())->first();

        if ($session === null) {
            return null;
        }

        // One write per hour is enough to know a session is still in use.
        if ($session->last_used_at === null || $session->last_used_at->lt(now()->subHour())) {
            $session->update(['last_used_at' => now()]);
        }

        return $session->student;
    }

    public function end(?string $token): void
    {
        if (filled($token)) {
            StudentSession::where('token_hash', hash('sha256', $token))->delete();
        }
    }

    public function endOthers(Student $student, ?string $keep): void
    {
        StudentSession::where('student_id', $student->id)
            ->where('token_hash', '!=', hash('sha256', (string) $keep))
            ->delete();
    }
}
