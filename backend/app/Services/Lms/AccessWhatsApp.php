<?php

namespace App\Services\Lms;

use App\Models\AccessDelivery;
use App\Models\Order;
use App\Models\User;
use App\Services\Kirimdev\KirimdevClient;
use App\Support\WhatsAppNumber;
use Illuminate\Support\Str;
use Throwable;

/**
 * Hands a buyer their class link over WhatsApp, next to the email.
 *
 * Automatic: after payment, through kirimdev with a Meta-approved template.
 * Manual: an admin presses "Kirim via WA" (kirimdev) or opens a wa.me chat
 * with the message filled in, for when the template or kirimdev is not ready.
 * Every attempt is a row in access_deliveries, so the panel shows what happened.
 */
class AccessWhatsApp
{
    public function __construct(private KirimdevClient $kirimdev, private LoginLink $links) {}

    /** Called once per paid order. Never throws: a WhatsApp problem must not undo a payment. */
    public function afterPayment(Order $order): ?AccessDelivery
    {
        try {
            if (! config('kirimdev.access_auto')) {
                return null;
            }

            $delivery = $this->open($order, 'auto');

            if ($delivery->status === 'queued') {
                $this->attempt($delivery);
            }

            return $delivery->refresh();
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /** "Kirim via WA" in the panel. An admin may correct the number first. */
    public function sendManually(Order $order, User $by, ?string $phone = null): AccessDelivery
    {
        $delivery = $this->open($order, 'manual', $by, $phone);

        if ($delivery->status === 'queued') {
            $this->attempt($delivery);
        }

        return $delivery->refresh();
    }

    /**
     * "Buka WhatsApp" in the panel: returns a wa.me link with the message
     * written out, for the admin to send from their own WhatsApp.
     */
    public function waMeLink(Order $order, User $by, ?string $phone = null): ?string
    {
        $student = $order->student;
        $number = WhatsAppNumber::normalize($phone ?? $student->whatsapp);

        if ($number === null) {
            return null;
        }

        $minutes = (int) config('lms.purchase_link_minutes');
        $url = $this->loginUrl($order, $minutes);

        AccessDelivery::create([
            'uuid' => (string) Str::uuid(),
            'student_id' => $student->id,
            'order_id' => $order->id,
            'course_id' => $order->course_id,
            'user_id' => $by->id,
            'mode' => 'wa_me',
            'status' => 'opened',
            'phone' => $number,
            'sent_at' => now(),
        ]);

        $text = implode("\n", [
            'Halo '.self::firstName($student->name).', pembayaran '.$order->purchaseTitle().' sudah kami terima dan kelasnya sudah terbuka.',
            '',
            'Masuk ke kelas lewat tautan ini:',
            $url,
            '',
            'Tautan berlaku '.self::lifetime($minutes).' dan hanya bisa dipakai sekali. Kalau sudah lewat, minta tautan baru di halaman Masuk dengan email '.$student->email.'.',
            '',
            'Pitcar Academy',
        ]);

        return 'https://wa.me/'.$number.'?text='.rawurlencode($text);
    }

    /** Picks up messages that are still queued, for the scheduler. Returns how many were tried. */
    public function retryPending(): int
    {
        // A row stuck in "sending" means the process died mid-request.
        AccessDelivery::where('status', 'sending')->where('updated_at', '<', now()->subMinutes(5))->update(['status' => 'queued']);

        $tried = 0;

        AccessDelivery::where('status', 'queued')
            ->where('updated_at', '<', now()->subMinute())
            ->orderBy('id')
            ->limit(20)
            ->get()
            ->each(function (AccessDelivery $delivery) use (&$tried) {
                $this->attempt($delivery);
                $tried++;
            });

        return $tried;
    }

    private function open(Order $order, string $mode, ?User $by = null, ?string $phone = null): AccessDelivery
    {
        $student = $order->student;
        $number = WhatsAppNumber::normalize($phone ?? $student->whatsapp);

        [$status, $error] = match (true) {
            $number === null => ['skipped', 'Nomor WhatsApp pembeli tidak valid'],
            ! $this->kirimdev->configured() => ['skipped', 'kirimdev belum diatur (KIRIMDEV_API_KEY, KIRIMDEV_PHONE_NUMBER_ID)'],
            default => ['queued', null],
        };

        return AccessDelivery::create([
            'uuid' => (string) Str::uuid(),
            'student_id' => $student->id,
            'order_id' => $order->id,
            'course_id' => $order->course_id,
            'user_id' => $by?->id,
            'mode' => $mode,
            'status' => $status,
            'phone' => $number,
            'last_error' => $error,
        ]);
    }

    private function attempt(AccessDelivery $delivery): void
    {
        // Claim the row so the scheduler and a web request never send it twice.
        $claimed = AccessDelivery::whereKey($delivery->id)->where('status', 'queued')
            ->update(['status' => 'sending', 'updated_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        $delivery->refresh();
        $order = $delivery->order;

        if ($order === null) {
            $delivery->update(['status' => 'failed', 'last_error' => 'Pesanan sudah dihapus']);

            return;
        }

        $minutes = (int) config('lms.purchase_link_minutes');
        $student = $order->student;

        $result = $this->kirimdev->sendTemplate(
            $delivery->phone,
            (string) config('kirimdev.access_template'),
            [
                self::firstName($student->name),
                $order->purchaseTitle(),
                $this->loginUrl($order, $minutes),
                self::lifetime($minutes),
                $student->email,
            ],
            // One key per attempt: a retry carries a new link, so it is a new message.
            $delivery->uuid.'-'.($delivery->attempts + 1),
        );

        $attempts = $delivery->attempts + 1;

        if ($result['ok']) {
            $delivery->update(['status' => 'sent', 'attempts' => $attempts, 'provider_message_id' => $result['id'], 'last_error' => null, 'sent_at' => now()]);

            return;
        }

        $delivery->update([
            'status' => $result['retry'] && $attempts < (int) config('kirimdev.max_attempts') ? 'queued' : 'failed',
            'attempts' => $attempts,
            'last_error' => $result['error'],
        ]);
    }

    private function loginUrl(Order $order, int $minutes): string
    {
        return config('lms.frontend_url').'/belajar/masuk/?token='.$this->links->issue($order->student, $minutes);
    }

    private static function firstName(string $name): string
    {
        return Str::before(trim($name), ' ') ?: 'Kak';
    }

    private static function lifetime(int $minutes): string
    {
        return $minutes >= 120 ? intdiv($minutes, 60).' jam' : $minutes.' menit';
    }
}
