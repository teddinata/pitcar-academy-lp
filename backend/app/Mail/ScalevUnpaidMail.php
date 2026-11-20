<?php

namespace App\Mail;

use App\Models\ScalevUnpaidOrder;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Sends a buyer who left a Scalev order unpaid to the new checkout, at today's price. */
class ScalevUnpaidMail extends Mailable
{
    public function __construct(public ScalevUnpaidOrder $order) {}

    public function envelope(): Envelope
    {
        $replyTo = config('mail.reply_to');

        return new Envelope(
            subject: 'Pesanan '.$this->title().' kamu belum selesai',
            replyTo: $replyTo ? [new Address($replyTo)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.scalev-unpaid', text: 'mail.scalev-unpaid-text', with: [
            'first' => $this->order->firstName(),
            'title' => $this->title(),
            'url' => $this->order->checkoutUrl('email'),
            'price' => $this->order->course ? 'Rp'.number_format((int) $this->order->course->price, 0, ',', '.') : null,
            'canReply' => filled(config('mail.reply_to')),
        ]);
    }

    private function title(): string
    {
        return $this->order->course?->title ?? ($this->order->product_name ?: 'kelas online Pitcar Academy');
    }
}
