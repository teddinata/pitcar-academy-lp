<?php

namespace App\Mail;

use App\Models\Student;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Tells a buyer from Scalev that their class now opens on Pitcar Academy's own site. */
class ScalevMoveMail extends Mailable
{
    /** @param  list<string>  $titles */
    public function __construct(public Student $student, public array $titles, public string $url, public int $minutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Kelasmu sekarang dibuka di Pitcar Academy');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.scalev-move', text: 'mail.scalev-move-text');
    }
}
