<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pull completed Scalev orders for the sales popup. Purchase history is
// sparse, so every three hours is plenty; the command also runs manually via
// `php artisan scalev:sync-purchases`. No-ops gracefully when SCALEV_API_KEY
// is not configured.
Schedule::command('scalev:sync-purchases')->everyThreeHours();

// VA and QRIS payments are only announced when they succeed, so orders that
// ran out of time are closed here, after one last check with DOKU.
Schedule::command('lms:settle-orders')->everyTenMinutes()->withoutOverlapping();

// Class-access WhatsApp messages that hit a kirimdev outage or rate limit get
// another try here. The first try happens right when the payment lands.
Schedule::command('lms:send-whatsapp-access')->everyMinute()->withoutOverlapping();
