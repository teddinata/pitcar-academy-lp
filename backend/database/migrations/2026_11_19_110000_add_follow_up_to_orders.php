<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // When the team last messaged a buyer who ordered but did not pay, and who did it.
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('followed_up_at')->nullable();
            $table->foreignId('followed_up_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('followed_up_by');
            $table->dropColumn('followed_up_at');
        });
    }
};
