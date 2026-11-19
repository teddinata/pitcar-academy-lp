<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bundles: at checkout a course can recommend another one at a lower add-on
 * price. An order now holds one or more courses, so each paid course is a
 * row of its own and the order's amount is their sum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            // The id the ad pixels already use for this course (content_ids), so reports keep one name per product.
            $table->string('tracking_id')->nullable()->after('slug');
        });

        Schema::create('course_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offer_course_id')->constrained('courses')->cascadeOnDelete();
            // What the add-on costs on top of the main course, not its own list price.
            $table->unsignedBigInteger('price');
            $table->string('headline')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['course_id', 'offer_course_id']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained();
            $table->foreignId('course_offer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind');
            $table->unsignedBigInteger('price');
            $table->timestamps();
            $table->unique(['order_id', 'course_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->json('attribution')->nullable()->after('claim_hash');
            $table->timestamp('meta_conversion_sent_at')->nullable()->after('paid_at');
        });

        // Orders placed before bundles were a single course for the full amount.
        $now = now();
        DB::table('orders')->orderBy('id')->each(function ($order) use ($now) {
            DB::table('order_items')->insert([
                'order_id' => $order->id,
                'course_id' => $order->course_id,
                'kind' => 'main',
                'price' => $order->amount,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['attribution', 'meta_conversion_sent_at']);
        });
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('course_offers');
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('tracking_id');
        });
    }
};
