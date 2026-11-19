<?php

namespace Tests\Feature\Lms;

use App\Enums\CourseStatus;
use App\Enums\EnrollmentSource;
use App\Enums\OrderStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LoginToken;
use App\Models\Order;
use App\Models\Student;
use App\Services\Lms\EnrollmentGrant;
use App\Services\Lms\LoginLink;
use App\Services\Lms\ProgressRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LmsFoundationTest extends TestCase
{
    use RefreshDatabase;

    /** A course with one module and four lessons, the first a free preview. */
    private function course(): Course
    {
        $course = Course::create(['title' => 'Kelas AC Mobil', 'slug' => 'kelas-ac-mobil', 'price' => 99000, 'status' => CourseStatus::Published]);
        $module = $course->modules()->create(['title' => 'Materi', 'sort' => 1]);

        foreach (range(1, 4) as $n) {
            $module->lessons()->create(['title' => "Video {$n}", 'sort' => $n, 'is_preview' => $n === 1]);
        }

        return $course;
    }

    private function student(string $email = 'pembeli@example.test'): Student
    {
        return Student::create(['name' => 'Pembeli', 'email' => $email]);
    }

    private function order(Student $student, Course $course): Order
    {
        return Order::create(['number' => 'ORD-1', 'student_id' => $student->id, 'course_id' => $course->id, 'amount' => $course->price]);
    }

    public function test_a_paid_order_opens_the_course_once_even_when_announced_twice(): void
    {
        $course = $this->course();
        $student = $this->student();
        $order = $this->order($student, $course);
        $grant = new EnrollmentGrant;

        $this->assertFalse($student->hasAccessTo($course));

        $grant->markPaid($order, 'DOKU-REF-1');
        $grant->markPaid($order, 'DOKU-REF-1');

        $this->assertTrue($student->hasAccessTo($course));
        $this->assertSame(1, Enrollment::count());
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame('DOKU-REF-1', $order->fresh()->gateway_reference);
        $this->assertSame(EnrollmentSource::Doku, Enrollment::first()->source);
    }

    public function test_revoking_closes_the_course_and_granting_again_reopens_it(): void
    {
        $course = $this->course();
        $student = $this->student();
        $grant = new EnrollmentGrant;

        $enrollment = $grant->grant($student, $course, EnrollmentSource::Scalev);
        $grant->revoke($enrollment);
        $this->assertFalse($student->hasAccessTo($course));

        $grant->grant($student, $course, EnrollmentSource::Manual);
        $this->assertTrue($student->hasAccessTo($course));
        $this->assertSame(1, Enrollment::count());
    }

    public function test_a_preview_lesson_is_watchable_without_buying_and_the_rest_is_not(): void
    {
        $course = $this->course();
        $student = $this->student();
        [$preview, $paid] = [$course->lessons()->where('is_preview', true)->first(), $course->lessons()->where('is_preview', false)->first()];

        $this->assertTrue($student->canWatch($preview));
        $this->assertFalse($student->canWatch($paid));

        (new EnrollmentGrant)->grant($student, $course, EnrollmentSource::Manual);
        $this->assertTrue($student->canWatch($paid));
    }

    public function test_progress_only_goes_up_and_a_lesson_finishes_at_the_configured_percent(): void
    {
        config(['lms.complete_at_percent' => 90]);
        $course = $this->course();
        $student = $this->student();
        $lesson = $course->lessons()->first();
        $recorder = new ProgressRecorder;

        $recorder->record($student, $lesson, 62, 300);
        $this->assertNull($recorder->record($student, $lesson, 40, 120)->completed_at);
        $this->assertSame(62, $lesson->progress()->first()->watched_percent);
        $this->assertSame(120, $lesson->progress()->first()->last_position_seconds);

        $done = $recorder->record($student, $lesson, 250, 900);
        $this->assertSame(100, $done->watched_percent);
        $this->assertNotNull($done->completed_at);
    }

    public function test_course_progress_is_the_average_across_all_lessons(): void
    {
        $course = $this->course();
        $student = $this->student();
        $lessons = $course->lessons()->orderBy('lessons.sort')->get();
        $recorder = new ProgressRecorder;

        $this->assertSame(0, $recorder->coursePercent($student, $course));

        $recorder->record($student, $lessons[0], 100, 0);
        $recorder->record($student, $lessons[1], 60, 0);

        $this->assertSame(40, $recorder->coursePercent($student, $course));
    }

    public function test_a_login_link_works_once_expires_and_is_stored_hashed(): void
    {
        config(['lms.login_link_minutes' => 30]);
        $student = $this->student();
        $links = new LoginLink;

        $token = $links->issue($student);
        $this->assertDatabaseMissing('login_tokens', ['token_hash' => $token]);
        $this->assertSame(hash('sha256', $token), LoginToken::first()->token_hash);

        $this->assertTrue($links->consume($token)->is($student));
        $this->assertNotNull($student->fresh()->last_login_at);
        $this->assertNull($links->consume($token));
        $this->assertNull($links->consume('bukan-token'));

        $expired = $links->issue($student);
        $this->travel(31)->minutes();
        $this->assertNull($links->consume($expired));
    }

    public function test_one_email_is_one_student_however_it_is_typed(): void
    {
        $this->student('  Pembeli@Example.Test ');

        $this->assertSame('pembeli@example.test', Student::first()->email);
    }
}
