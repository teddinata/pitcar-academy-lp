<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // When the follow-up email went out. Kept apart from followed_up_at,
        // which records the WhatsApp follow-up a person sends by hand.
        Schema::table('scalev_unpaid_orders', function (Blueprint $table) {
            $table->timestamp('emailed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('scalev_unpaid_orders', fn (Blueprint $table) => $table->dropColumn('emailed_at'));
    }
};
