<?php

namespace App\Services\Lms;

use App\Mail\LoginLinkMail;
use App\Models\Course;
use App\Models\LoginToken;
use App\Models\Student;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class LoginLink
{
    /** Returns the plain token to put in the link. It is not stored anywhere. */
    public function issue(Student $student, ?int $minutes = null): string
    {
        $token = Str::random(48);

        LoginToken::create([
            'student_id' => $student->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes($minutes ?? config('lms.login_link_minutes')),
        ]);

        return $token;
    }

    /** Mails a link. With a course, the mail announces that the class is open. */
    public function send(Student $student, ?Course $course = null, ?string $title = null): void
    {
        $minutes = (int) config($course ? 'lms.purchase_link_minutes' : 'lms.login_link_minutes');
        $url = config('lms.frontend_url').'/belajar/masuk/?token='.$this->issue($student, $minutes);

        try {
            Mail::to($student->email)->send(new LoginLinkMail($student, $url, $minutes, $course, $title));
        } catch (Throwable $e) {
            // A mail outage must not undo a payment; the buyer can ask for a new link.
            report($e);
        }
    }

    /** A link works once. Returns null for an unknown, used, or expired token. */
    public function consume(string $token): ?Student
    {
        $row = LoginToken::where('token_hash', hash('sha256', $token))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($row === null) {
            return null;
        }

        $row->update(['used_at' => now()]);
        $row->student->update(['last_login_at' => now()]);

        return $row->student;
    }
}
