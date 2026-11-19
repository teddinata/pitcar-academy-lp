<?php

namespace App\Models;

use App\Enums\CourseStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A course recommended at checkout of another, at an add-on price.
 * Buying `course` with this offer ticked costs course price + `price`.
 */
class CourseOffer extends Model
{
    protected $fillable = ['course_id', 'offer_course_id', 'price', 'headline', 'is_active', 'sort'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function offerCourse(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'offer_course_id');
    }

    /** Switched on, and pointing at a course that is itself on sale. */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereHas('offerCourse', fn (Builder $q) => $q->where('status', CourseStatus::Published))
            ->orderBy('sort')
            ->orderBy('id');
    }
}
