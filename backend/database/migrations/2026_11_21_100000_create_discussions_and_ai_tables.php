<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Class discussions. A row with no thread_id opens a thread; the rest
        // reply inside it. reply_to_id names the reply being answered, so a
        // conversation inside a thread stays readable without deeper nesting.
        Schema::create('discussion_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('thread_id')->nullable()->constrained('discussion_posts')->cascadeOnDelete();
            $table->foreignId('reply_to_id')->nullable()->constrained('discussion_posts')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->unsignedInteger('reply_count')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('delete_reason', 40)->nullable();
            $table->timestamps();

            $table->index(['course_id', 'thread_id', 'last_activity_at']);
        });

        Schema::create('discussion_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('discussion_posts')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 300)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['post_id', 'student_id']);
        });

        // AI Pitcar Academy. A student talks about one class; a team member
        // asks about the business. Messages keep the text shown and the tokens
        // spent, for the daily limit and the bill.
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->string('role', 16);
            $table->longText('content')->nullable();
            $table->string('status', 16)->default('done');
            $table->text('error')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cache_read_tokens')->default(0);
            $table->unsignedInteger('cache_write_tokens')->default(0);
            $table->timestamps();
        });

        // A class PDF uploaded once to Anthropic's Files API, then cited by id.
        Schema::table('lesson_attachments', function (Blueprint $table) {
            $table->string('ai_file_id')->nullable();
        });

        // Tokens for the read-only MCP server. Only the hash is kept.
        Schema::create('mcp_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        // When a buyer moved from Scalev was told their class now lives here.
        Schema::table('students', function (Blueprint $table) {
            $table->timestamp('scalev_announced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('students', fn (Blueprint $table) => $table->dropColumn('scalev_announced_at'));
        Schema::dropIfExists('mcp_tokens');
        Schema::table('lesson_attachments', fn (Blueprint $table) => $table->dropColumn('ai_file_id'));
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
        Schema::dropIfExists('discussion_reports');
        Schema::dropIfExists('discussion_posts');
    }
};
