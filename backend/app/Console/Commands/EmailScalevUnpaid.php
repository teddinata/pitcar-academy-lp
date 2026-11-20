<?php

namespace App\Console\Commands;

use App\Models\ScalevUnpaidOrder;
use App\Services\Lms\ScalevUnpaidMailer;
use Illuminate\Console\Command;
use Throwable;

/**
 * The follow-up email to every order on "Belum bayar di Scalev" that has an
 * address and has not been emailed. Run --dry-run first to see who.
 */
class EmailScalevUnpaid extends Command
{
    protected $signature = 'scalev:email-unpaid
        {--dry-run : List who would get the email without sending}
        {--limit=0 : Send to at most this many orders in this run}';

    protected $description = 'Email unpaid Scalev orders from the last 7 days a link to the new checkout';

    public function handle(ScalevUnpaidMailer $mailer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));
        $query = ScalevUnpaidOrder::toEmail()->with('course')->orderByDesc('ordered_at');
        $total = (clone $query)->count();

        $this->info(($dryRun ? 'Mode uji. ' : '')."{$total} pesanan belum bayar belum dikirimi email.");
        $skipped = ScalevUnpaidOrder::toFollowUp()->whereNull('email')->count();
        if ($skipped > 0) {
            $this->line("{$skipped} pesanan tidak punya email; follow up lewat WhatsApp dari halaman admin.");
        }

        $sent = 0;
        $failed = 0;

        foreach ($query->when($limit > 0, fn ($q) => $q->limit($limit))->get() as $order) {
            $what = $order->course?->title ?? $order->product_name;

            if ($dryRun) {
                $this->line("- {$order->customer_name} <{$order->email}>: {$what}");

                continue;
            }

            try {
                $mailer->send($order) && $sent++;
            } catch (Throwable $e) {
                report($e);
                $failed++;
                $this->warn("Gagal ke {$order->email}: {$e->getMessage()}");
            }

            // Resend allows a few requests a second; stay well under it.
            usleep(600_000);
        }

        if (! $dryRun) {
            $this->info("Terkirim: {$sent}. Gagal: {$failed}.".($failed ? ' Jalankan lagi untuk mencoba yang gagal.' : ''));
        }

        return self::SUCCESS;
    }
}
