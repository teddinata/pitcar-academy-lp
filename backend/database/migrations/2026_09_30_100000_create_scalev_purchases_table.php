<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Local mirror of paid Scalev orders, only the fields the sales popup
        // and the buyer counter need. order_id is the Scalev UUIDv7 primary
        // key, so re-syncing the same order updates instead of duplicating.
        Schema::create('scalev_purchases', function (Blueprint $table) {
            $table->string('order_id')->primary();
            $table->string('customer_name');
            $table->string('customer_city')->nullable();
            $table->string('product_name')->nullable();
            $table->unsignedBigInteger('amount')->nullable();
            $table->timestamp('purchased_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scalev_purchases');
    }
};
