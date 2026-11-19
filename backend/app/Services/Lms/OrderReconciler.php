<?php

namespace App\Services\Lms;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Doku\DokuCheckout;
use Throwable;

/**
 * DOKU only announces VA and QRIS payments that succeed, and a notification
 * can be lost. This asks DOKU directly and settles a pending order either way.
 */
class OrderReconciler
{
    public function __construct(private DokuCheckout $doku, private EnrollmentGrant $grant) {}

    public function refresh(Order $order): Order
    {
        if ($order->status !== OrderStatus::Pending) {
            return $order;
        }

        try {
            $status = $this->doku->configured() ? $this->doku->status($order) : null;
        } catch (Throwable $e) {
            report($e);
            $status = null;
        }

        if ($status === 'SUCCESS') {
            $this->grant->markPaid($order, via: Order::PAID_VIA_STATUS_CHECK);
        } elseif ($status === 'EXPIRED' || ($order->expires_at && $order->expires_at->isPast())) {
            // A payment made in the last seconds still arrives as a notification and wins.
            $order->update(['status' => OrderStatus::Expired]);
        }

        return $order->refresh();
    }
}
