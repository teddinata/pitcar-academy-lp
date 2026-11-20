<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One rating per student per class. Sending again updates it.
        Schema::create('course_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('body')->nullable();
            // The student's consent to show the review publicly. Nothing is shown yet.
            $table->boolean('may_publish')->default(false);
            $table->timestamps();
            $table->unique(['student_id', 'course_id']);
        });

        Schema::create('student_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            // topic (a lesson they want), feedback, problem.
            $table->string('kind', 20);
            $table->text('body');
            // new, reviewing, done.
            $table->string('status', 20)->default('new')->index();
            $table->text('reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_requests');
        Schema::dropIfExists('course_reviews');
    }
};
