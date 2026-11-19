<?php

namespace App\Services\Lms;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Student;

class ProgressRecorder
{
    /**
     * Records how far a student has watched. The percentage only ever goes
     * up: rewinding to rewatch a part must not undo a finished lesson.
     */
    public function record(Student $student, Lesson $lesson, int $percent, int $positionSeconds): LessonProgress
    {
        $percent = max(0, min(100, $percent));
        $progress = LessonProgress::firstOrNew(['student_id' => $student->id, 'lesson_id' => $lesson->id]);

        $progress->watched_percent = max((int) $progress->watched_percent, $percent);
        $progress->last_position_seconds = max(0, $positionSeconds);

        if ($progress->completed_at === null && $progress->watched_percent >= config('lms.complete_at_percent')) {
            $progress->completed_at = now();
        }

        $progress->save();

        return $progress;
    }

    /** Course progress is the average watched percentage across all its lessons. */
    public function coursePercent(Student $student, Course $course): int
    {
        $lessonIds = $course->lessons()->pluck('lessons.id');

        if ($lessonIds->isEmpty()) {
            return 0;
        }

        $watched = LessonProgress::where('student_id', $student->id)->whereIn('lesson_id', $lessonIds)->sum('watched_percent');

        return (int) round($watched / $lessonIds->count());
    }
}
