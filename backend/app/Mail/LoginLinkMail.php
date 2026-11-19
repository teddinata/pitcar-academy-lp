<?php

namespace App\Mail;

use App\Models\Course;
use App\Models\Student;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class LoginLinkMail extends Mailable
{
    /** With a course, this is the "your class is open" mail sent after payment. */
    public function __construct(public Student $student, public string $url, public int $minutes, public ?Course $course = null, public ?string $title = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->course
            ? 'Kelas kamu sudah terbuka: '.($this->title ?? $this->course->title)
            : 'Tautan masuk Pitcar Academy');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.login-link', text: 'mail.login-link-text');
    }
}
