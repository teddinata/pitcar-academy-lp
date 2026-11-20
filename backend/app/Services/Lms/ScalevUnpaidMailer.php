<?php

namespace App\Services\Lms;

use App\Jobs\SendScalevUnpaidEmail;
use App\Mail\ScalevUnpaidMail;
use App\Models\ScalevUnpaidOrder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * The follow-up email for unpaid Scalev orders. Each order gets it once:
 * an order is checked again right before sending, so one that was paid,
 * already emailed, or bought here in the meantime is skipped.
 */
class ScalevUnpaidMailer
{
    /** Sends now. Returns false when the order no longer needs it. */
    public function send(ScalevUnpaidOrder $order): bool
    {
        $order = ScalevUnpaidOrder::toEmail()->with('course')->find($order->getKey());

        if ($order === null) {
            return false;
        }

        Mail::to($order->email, $order->customer_name)->send(new ScalevUnpaidMail($order));
        $order->forceFill(['emailed_at' => now()])->save();

        return true;
    }

    /**
     * Queues the emails a second apart, so Resend's rate limit is never
     * reached however many are picked. Returns how many were queued.
     *
     * @param  Collection<int, ScalevUnpaidOrder>  $orders
     */
    public function queue(Collection $orders): int
    {
        $ids = ScalevUnpaidOrder::toEmail()->whereKey($orders->modelKeys())->pluck('order_id');

        foreach ($ids->values() as $i => $id) {
            SendScalevUnpaidEmail::dispatch($id)->delay(now()->addSeconds($i));
        }

        return $ids->count();
    }
}
