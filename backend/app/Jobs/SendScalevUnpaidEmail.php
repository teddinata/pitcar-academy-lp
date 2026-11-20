<?php

namespace App\Jobs;

use App\Models\ScalevUnpaidOrder;
use App\Services\Lms\ScalevUnpaidMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendScalevUnpaidEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public string $orderId) {}

    public function handle(ScalevUnpaidMailer $mailer): void
    {
        if ($order = ScalevUnpaidOrder::find($this->orderId)) {
            $mailer->send($order);
        }
    }
}
