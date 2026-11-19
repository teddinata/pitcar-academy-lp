<?php

namespace App\Models;

use App\Enums\CourseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Course extends Model
{
    protected $fillable = ['title', 'slug', 'tracking_id', 'summary', 'price', 'strike_price', 'cover_path', 'status'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'strike_price' => 'integer',
            'status' => CourseStatus::class,
        ];
    }

    public function modules(): HasMany
    {
        return $this->hasMany(CourseModule::class)->orderBy('sort');
    }

    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, CourseModule::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(LessonAttachment::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** Courses recommended at this course's checkout. */
    public function offers(): HasMany
    {
        return $this->hasMany(CourseOffer::class);
    }

    /** The product id the ad pixels report for this course. */
    public function trackingId(): string
    {
        return $this->tracking_id ?: $this->slug;
    }
}
