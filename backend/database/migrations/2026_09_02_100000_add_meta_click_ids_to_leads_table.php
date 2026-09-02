<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meta's browser cookies, forwarded so the Conversions API can match a
 * server-side event back to the ad that produced it. Without them match
 * quality drops to name and phone alone, and attribution gets guessy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // _fbp is set by the pixel; _fbc is derived from the fbclid on the
            // landing URL. Both are opaque strings, neither identifies a
            // person on its own.
            $table->string('fbp', 255)->nullable()->after('utm_term');
            $table->string('fbc', 255)->nullable()->after('fbp');

            // Meta dedupes a browser event against a server event by matching
            // event ids; this records what we sent so a retry stays the same
            // event rather than becoming a second conversion.
            $table->timestamp('meta_conversion_sent_at')->nullable()->after('fbc');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['fbp', 'fbc', 'meta_conversion_sent_at']);
        });
    }
};
