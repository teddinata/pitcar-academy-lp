<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per page view on the public site. No IP, no cookie: the
        // visitor id is a random value the browser keeps in localStorage.
        Schema::create('page_visits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('visitor_id')->index();
            // The day in the display timezone, so daily totals group in SQL.
            $table->date('visit_date')->index();
            $table->string('path', 191);
            // meta, google, tiktok, whatsapp, direct, other, or a utm_source.
            $table->string('source', 40);
            $table->string('referrer_host', 191)->nullable();
            $table->string('utm_campaign', 191)->nullable();
            $table->string('device', 10);
            // Seconds the page was on screen, capped so a forgotten tab does not skew averages.
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->timestamp('started_at');
            $table->index(['visit_date', 'path']);
        });

        // Meta Ads spend per campaign per day, as Meta reports it.
        Schema::create('ad_spend_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->string('campaign_id', 40);
            $table->string('campaign_name');
            $table->decimal('spend', 16, 2)->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedInteger('link_clicks')->default(0);
            // Purchases Meta attributes to the campaign (pixel and Conversions API).
            $table->unsignedInteger('purchases')->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->timestamps();
            $table->unique(['date', 'campaign_id']);
        });

        // Settings the admin changes from the panel. Secrets are stored encrypted.
        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('ad_spend_daily');
        Schema::dropIfExists('page_visits');
    }
};
