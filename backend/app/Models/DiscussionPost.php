<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One message in a class discussion: a thread (thread_id null) or a reply in
 * one. Removed messages keep their row so the thread around them still reads;
 * only the body is hidden.
 */
class DiscussionPost extends Model
{
    /** Why the team removed a message. Students see only that it was removed. */
    public const REASONS = [
        'hate' => 'Ujaran kebencian atau SARA',
        'abuse' => 'Menghina atau menyerang orang',
        'spam' => 'Spam atau promosi',
        'personal' => 'Membagikan data pribadi',
        'other' => 'Lainnya',
    ];

    /** Removed by the author themselves. */
    public const REASON_AUTHOR = 'author';

    protected $fillable = ['course_id', 'lesson_id', 'thread_id', 'reply_to_id', 'student_id', 'user_id', 'body', 'reply_count', 'last_activity_at', 'deleted_at', 'deleted_by', 'delete_reason'];

    protected function casts(): array
    {
        return ['last_activity_at' => 'datetime', 'deleted_at' => 'datetime', 'reply_count' => 'integer'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(self::class, 'thread_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'thread_id')->orderBy('id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(DiscussionReport::class, 'post_id');
    }

    public function scopeThreads(Builder $query): Builder
    {
        return $query->whereNull('thread_id');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    /** Reported and not yet looked at by the team. */
    public function scopeReported(Builder $query): Builder
    {
        return $query->whereNull('deleted_at')->whereHas('reports', fn ($q) => $q->whereNull('resolved_at'));
    }

    /** A thread nobody from the team has answered yet. */
    public function scopeUnanswered(Builder $query): Builder
    {
        return $query->threads()->visible()
            ->whereDoesntHave('replies', fn ($q) => $q->whereNotNull('user_id')->whereNull('deleted_at'));
    }

    public function isThread(): bool
    {
        return $this->thread_id === null;
    }

    public function isRemoved(): bool
    {
        return $this->deleted_at !== null;
    }

    public function authorName(): string
    {
        return $this->user_id ? ($this->user?->name ?? 'Tim Pitcar Academy') : ($this->student?->name ?? 'Siswa');
    }

    /** Takes the message down; replies under a thread stay. */
    public function remove(?User $by, string $reason): void
    {
        if ($this->isRemoved()) {
            return;
        }

        $this->update(['deleted_at' => now(), 'deleted_by' => $by?->id, 'delete_reason' => $reason]);
        $this->reports()->whereNull('resolved_at')->update(['resolved_at' => now()]);

        if (! $this->isThread()) {
            $this->thread?->recount();
        }
    }

    /** A reply from the team inside this thread. */
    public function replyAsTeam(User $user, string $body, ?int $replyTo = null): self
    {
        $thread = $this->isThread() ? $this : $this->thread;
        $reply = self::create([
            'course_id' => $thread->course_id,
            'lesson_id' => $thread->lesson_id,
            'thread_id' => $thread->id,
            'reply_to_id' => $replyTo ?? ($this->isThread() ? null : $this->id),
            'user_id' => $user->id,
            'body' => trim($body),
        ]);
        $thread->update(['last_activity_at' => now()]);
        $thread->recount();

        return $reply;
    }

    public function recount(): void
    {
        $this->update(['reply_count' => $this->replies()->whereNull('deleted_at')->count()]);
    }
}
