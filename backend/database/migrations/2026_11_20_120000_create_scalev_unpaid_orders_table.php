<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Orders left unpaid on the Scalev checkout this LMS replaces, kept
        // for a week so the team can send the buyer to the new checkout.
        Schema::create('scalev_unpaid_orders', function (Blueprint $table) {
            $table->string('order_id')->primary();
            $table->string('customer_name');
            $table->string('email')->nullable()->index();
            $table->string('whatsapp', 20)->nullable();
            $table->string('product_name')->nullable();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('amount')->nullable();
            $table->timestamp('ordered_at')->index();
            $table->timestamp('followed_up_at')->nullable();
            $table->foreignId('followed_up_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scalev_unpaid_orders');
    }
};
