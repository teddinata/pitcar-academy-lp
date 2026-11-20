<?php

namespace Tests\Feature\Lms;

use App\Enums\CourseStatus;
use App\Enums\EnrollmentSource;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Filament\Resources\CourseReviews\CourseReviewResource;
use App\Filament\Resources\CourseReviews\Pages\ListCourseReviews;
use App\Filament\Resources\StudentRequests\Pages\ListStudentRequests;
use App\Filament\Resources\StudentRequests\StudentRequestResource;
use App\Models\Course;
use App\Models\CourseReview;
use App\Models\Student;
use App\Models\StudentRequest;
use App\Models\User;
use App\Services\Lms\StudentSessions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LearnFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function course(string $slug = 'kelas-online-ac'): Course
    {
        return Course::create(['title' => 'Kelas '.$slug, 'slug' => $slug, 'price' => 99000, 'status' => CourseStatus::Published]);
    }

    private function student(?Course $enrolledIn = null, string $email = 'siswa@example.test'): Student
    {
        $student = Student::create(['name' => 'Siswa', 'email' => $email]);

        if ($enrolledIn) {
            $student->enrollments()->create(['course_id' => $enrolledIn->id, 'source' => EnrollmentSource::Manual, 'granted_at' => now()]);
        }

        return $student;
    }

    private function signedIn(Student $student): array
    {
        return ['Authorization' => 'Bearer '.app(StudentSessions::class)->start($student)];
    }

    public function test_a_student_rates_their_class_and_can_change_it(): void
    {
        $course = $this->course();
        $student = $this->student($course);
        $auth = $this->signedIn($student);

        $this->postJson("/api/learn/courses/{$course->slug}/review", ['rating' => 4, 'body' => '  Jelas sekali. ', 'may_publish' => true], $auth)
            ->assertOk()
            ->assertJsonPath('review.rating', 4)
            ->assertJsonPath('review.body', 'Jelas sekali.')
            ->assertJsonPath('review.may_publish', true);

        $this->postJson("/api/learn/courses/{$course->slug}/review", ['rating' => 5, 'body' => ''], $auth)->assertOk();

        $review = CourseReview::sole();
        $this->assertSame(5, $review->rating);
        $this->assertNull($review->body);
        $this->assertFalse($review->may_publish);

        $this->getJson("/api/learn/courses/{$course->slug}", $auth)->assertOk()->assertJsonPath('review.rating', 5);
    }

    public function test_the_class_page_has_no_review_before_one_is_sent(): void
    {
        $course = $this->course();
        $student = $this->student($course);

        $this->getJson("/api/learn/courses/{$course->slug}", $this->signedIn($student))->assertOk()->assertJsonPath('review', null);
    }

    public function test_a_rating_needs_access_and_a_value_from_one_to_five(): void
    {
        $owned = $this->course();
        $other = $this->course('kelas-online-inspeksi');
        $auth = $this->signedIn($this->student($owned));

        $this->postJson("/api/learn/courses/{$other->slug}/review", ['rating' => 5], $auth)->assertForbidden();
        $this->postJson("/api/learn/courses/{$owned->slug}/review", ['rating' => 6], $auth)->assertUnprocessable()->assertJsonValidationErrors('rating');
        $this->postJson("/api/learn/courses/{$owned->slug}/review", [], $auth)->assertUnprocessable()->assertJsonValidationErrors('rating');
        $this->postJson("/api/learn/courses/{$owned->slug}/review", ['rating' => 3])->assertUnauthorized();

        $this->assertSame(0, CourseReview::count());
    }

    public function test_a_student_sends_a_request_about_their_class_and_sees_it_listed(): void
    {
        $course = $this->course();
        $student = $this->student($course);
        $auth = $this->signedIn($student);

        $this->postJson('/api/learn/requests', ['kind' => 'topic', 'course' => $course->slug, 'body' => 'Tolong bahas cara cek kebocoran freon.'], $auth)
            ->assertCreated()
            ->assertJsonPath('request.status', 'new')
            ->assertJsonPath('request.course.slug', $course->slug);

        $this->postJson('/api/learn/requests', ['kind' => 'problem', 'body' => 'Video ketiga tidak bisa diputar di HP saya.'], $auth)
            ->assertCreated()
            ->assertJsonPath('request.course', null);

        $this->getJson('/api/learn/requests', $auth)
            ->assertOk()
            ->assertJsonCount(2, 'requests')
            ->assertJsonPath('requests.0.kind', 'problem');
    }

    public function test_a_request_is_checked_before_it_is_saved(): void
    {
        $owned = $this->course();
        $other = $this->course('kelas-online-inspeksi');
        $auth = $this->signedIn($this->student($owned));

        $this->postJson('/api/learn/requests', ['kind' => 'gossip', 'body' => 'Pesan yang cukup panjang.'], $auth)->assertJsonValidationErrors('kind');
        $this->postJson('/api/learn/requests', ['kind' => 'feedback', 'body' => 'pendek'], $auth)->assertJsonValidationErrors('body');
        $this->postJson('/api/learn/requests', ['kind' => 'feedback', 'course' => $other->slug, 'body' => 'Pesan yang cukup panjang.'], $auth)->assertJsonValidationErrors('course');

        $this->assertSame(0, StudentRequest::count());
    }

    public function test_a_student_only_sees_their_own_requests(): void
    {
        $course = $this->course();
        $mine = $this->student($course);
        $theirs = $this->student($course, 'lain@example.test');
        $theirs->requests()->create(['kind' => 'feedback', 'body' => 'Milik siswa lain.']);

        $this->getJson('/api/learn/requests', $this->signedIn($mine))->assertOk()->assertJsonCount(0, 'requests');
    }

    public function test_only_managers_and_admins_see_feedback_in_the_panel(): void
    {
        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->create(['role' => UserRole::Consultant]));
        $this->assertFalse(StudentRequestResource::canAccess());
        $this->assertFalse(CourseReviewResource::canAccess());

        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]));
        $this->assertTrue(StudentRequestResource::canAccess());
        $this->assertTrue(CourseReviewResource::canAccess());
    }

    public function test_the_team_replies_and_the_student_reads_it(): void
    {
        Filament::setCurrentPanel('admin');
        $course = $this->course();
        $student = $this->student($course);
        $request = $student->requests()->create(['kind' => 'topic', 'course_id' => $course->id, 'body' => 'Tolong bahas kompresor.']);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $this->assertSame('1', StudentRequestResource::getNavigationBadge());

        Livewire::test(ListStudentRequests::class)
            ->assertCanSeeTableRecords([$request])
            ->callTableAction('respond', $request, ['status' => 'done', 'reply' => 'Sudah masuk daftar video bulan depan.'])
            ->assertHasNoTableActionErrors();

        $request->refresh();
        $this->assertSame(RequestStatus::Done, $request->status);
        $this->assertSame($admin->id, $request->replied_by);
        $this->assertNotNull($request->replied_at);
        $this->assertNull(StudentRequestResource::getNavigationBadge());

        $this->getJson('/api/learn/requests', $this->signedIn($student))
            ->assertJsonPath('requests.0.reply', 'Sudah masuk daftar video bulan depan.')
            ->assertJsonPath('requests.0.status', 'done');
    }

    public function test_the_ratings_list_opens(): void
    {
        Filament::setCurrentPanel('admin');
        $course = $this->course();
        $review = CourseReview::create(['student_id' => $this->student($course)->id, 'course_id' => $course->id, 'rating' => 2, 'body' => 'Suara videonya kecil.']);
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

        Livewire::test(ListCourseReviews::class)->assertCanSeeTableRecords([$review]);
    }
}
