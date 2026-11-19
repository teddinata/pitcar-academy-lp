<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonProgress extends Model
{
    protected $table = 'lesson_progress';

    protected $fillable = ['student_id', 'lesson_id', 'watched_percent', 'last_position_seconds', 'completed_at'];

    protected function casts(): array
    {
        return [
            'watched_percent' => 'integer',
            'last_position_seconds' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
