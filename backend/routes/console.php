<?php

use App\Models\PageVisit;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pull recent Scalev orders: completed ones feed the sales popup and open the
// class in the LMS, unpaid ones go on the follow-up list. Hourly so that list
// is fresh; also runs manually via `php artisan scalev:sync-purchases`.
// No-ops gracefully when SCALEV_API_KEY is not configured.
Schedule::command('scalev:sync-purchases')->hourly()->withoutOverlapping();

// VA and QRIS payments are only announced when they succeed, so orders that
// ran out of time are closed here, after one last check with DOKU.
Schedule::command('lms:settle-orders')->everyTenMinutes()->withoutOverlapping();

// Class-access WhatsApp messages that hit a kirimdev outage or rate limit get
// another try here. The first try happens right when the payment lands.
Schedule::command('lms:send-whatsapp-access')->everyMinute()->withoutOverlapping();

// Meta reports spend and purchases late and revises the last few days, so the
// last three days are pulled again every hour. No-ops until an admin saves the
// ad account and token (Pengaturan › Iklan Meta).
Schedule::command('meta:sync-ads --days=3')->hourly()->withoutOverlapping();

// Page views are kept 13 months, enough to compare a month with the same month last year.
Schedule::call(fn () => PageVisit::where('visit_date', '<', now()->subMonths(13)->toDateString())->delete())
    ->daily()->name('analytics:prune-visits');
