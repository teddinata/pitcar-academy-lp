<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $fillable = ['name', 'email', 'whatsapp', 'last_login_at'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['last_login_at' => 'datetime', 'password_changed_at' => 'datetime'];
    }

    /**
     * Buyers type their address differently at checkout and at login; one
     * spelling means one account.
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value) => mb_strtolower(trim($value)));
    }

    /** none, system (still the one shown after payment) or own. */
    public function passwordKind(): string
    {
        return $this->password === null ? 'none' : ($this->password_changed_at === null ? 'system' : 'own');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function hasAccessTo(Course $course): bool
    {
        return $this->enrollments()->where('course_id', $course->id)->whereNull('revoked_at')->exists();
    }

    public function canWatch(Lesson $lesson): bool
    {
        return $lesson->is_preview || $this->hasAccessTo($lesson->course());
    }
}
