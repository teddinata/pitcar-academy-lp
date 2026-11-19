<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Lms\OrderReconciler;
use Illuminate\Console\Command;

class SettlePendingOrders extends Command
{
    protected $signature = 'lms:settle-orders';

    protected $description = 'Ask DOKU about pending orders past their deadline, then mark them paid or expired';

    public function handle(OrderReconciler $reconciler): int
    {
        $settled = 0;

        Order::where('status', OrderStatus::Pending)
            ->where(fn ($q) => $q->where('expires_at', '<', now())->orWhere(
                fn ($q) => $q->whereNull('expires_at')->where('created_at', '<', now()->subDay())
            ))
            ->each(function (Order $order) use ($reconciler, &$settled) {
                if ($reconciler->refresh($order)->status !== OrderStatus::Pending) {
                    $settled++;
                } elseif ($order->expires_at === null) {
                    // DOKU never opened a payment page for it, so nothing can still arrive.
                    $order->update(['status' => OrderStatus::Expired]);
                    $settled++;
                }
            });

        $this->info("Settled {$settled} order(s).");

        return self::SUCCESS;
    }
}
