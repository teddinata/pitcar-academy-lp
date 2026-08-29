<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('education_consultants', function (Blueprint $table) {
            // Lets a consultant log into the dashboard and see the leads routed
            // to them. Nullable so a consultant can exist as a routing target
            // before they have an account.
            $table->foreignId('user_id')->nullable()->after('id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('education_consultants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
