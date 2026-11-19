<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('strike_price')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('course_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_module_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('youtube_url')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            // A preview lesson can be watched without buying the course.
            $table->boolean('is_preview')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        // A file belongs to the whole course, or to one lesson of it.
        Schema::create('lesson_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('path');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });

        // Buyers are not admin users: they have no password and never see Filament.
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('whatsapp')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained();
            $table->unsignedBigInteger('amount');
            $table->string('status')->default('pending');
            $table->string('gateway')->default('doku');
            $table->string('gateway_reference')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        // One row per student and course. Revoking keeps the row, so the history
        // of who had access, and why, survives.
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source');
            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'course_id']);
        });

        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('watched_percent')->default(0);
            $table->unsignedInteger('last_position_seconds')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'lesson_id']);
        });

        // Only the hash is stored, so a database leak does not hand out logins.
        Schema::create('login_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['login_tokens', 'lesson_progress', 'enrollments', 'orders', 'students', 'lesson_attachments', 'lessons', 'course_modules', 'courses'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
