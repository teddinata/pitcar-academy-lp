<?php

namespace App\Models;

use App\Enums\RequestKind;
use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A lesson idea, feedback or a problem a student sends from the learning area. */
class StudentRequest extends Model
{
    protected $fillable = ['student_id', 'course_id', 'kind', 'body', 'status', 'reply', 'replied_at', 'replied_by'];

    protected $attributes = ['status' => 'new'];

    protected function casts(): array
    {
        return [
            'kind' => RequestKind::class,
            'status' => RequestStatus::class,
            'replied_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function repliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }
}
