<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The short form asks one "how ready are you" question instead of separate
     * timeline and investment questions, and drops "current activity" entirely
     * — a consultant can ask that on WhatsApp for free.
     *
     * The old columns stay, nullable, because leads already captured under the
     * previous form still hold real answers and reporting should not lose them.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('readiness', 32)->nullable()->after('goal');
            $table->index('readiness');
        });

        // Backfill so historical leads are comparable with new ones. Investment
        // readiness is the closer match: it already carried the "can you commit"
        // signal that `readiness` now owns.
        DB::table('leads')->whereNull('readiness')->update([
            'readiness' => DB::raw(match (DB::connection()->getDriverName()) {
                'sqlite', 'pgsql' => "CASE investment_readiness
                    WHEN 'ready' THEN 'nearest_batch'
                    WHEN 'installment' THEN 'need_payment_plan'
                    WHEN 'family_discussion' THEN 'family_discussion'
                    ELSE 'exploring' END",
                default => "CASE investment_readiness
                    WHEN 'ready' THEN 'nearest_batch'
                    WHEN 'installment' THEN 'need_payment_plan'
                    WHEN 'family_discussion' THEN 'family_discussion'
                    ELSE 'exploring' END",
            }),
        ]);

        Schema::table('leads', function (Blueprint $table) {
            $table->string('activity', 32)->nullable()->change();
            $table->string('timeline', 32)->nullable()->change();
            $table->string('investment_readiness', 32)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['readiness']);
            $table->dropColumn('readiness');
        });
    }
};
