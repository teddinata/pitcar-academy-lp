<?php

namespace Tests\Feature\Lms;

use App\Enums\CourseStatus;
use App\Enums\EnrollmentSource;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Filament\Resources\Courses\RelationManagers\StudentsRelationManager;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Course;
use App\Models\CourseReview;
use App\Models\LessonProgress;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Services\Lms\EnrollmentGrant;
use App\Services\Lms\StudentSessions;
use App\Services\Lms\TeamAlerts;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/** The admin side of selling classes: alerts, unpaid orders to follow up, and who is studying. */
class TeamFollowUpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['kirimdev.api_key' => null]);
        Filament::setCurrentPanel('admin');
    }

    private function course(string $slug = 'kelas-online-ac'): Course
    {
        $course = Course::create(['title' => 'Kelas '.$slug, 'slug' => $slug, 'price' => 99000, 'status' => CourseStatus::Published]);
        $module = $course->modules()->create(['title' => 'Materi', 'sort' => 1]);
        foreach ([1, 2] as $n) {
            $module->lessons()->create(['title' => "Video {$n}", 'sort' => $n, 'youtube_url' => 'https://youtu.be/abcdefghijk']);
        }

        return $course;
    }

    private function order(Course $course, string $email, OrderStatus $status, string $whatsapp = '6281200000000'): Order
    {
        $student = Student::firstOrCreate(['email' => $email], ['name' => 'Budi Santoso', 'whatsapp' => $whatsapp]);

        return Order::create([
            'number' => 'PA'.strtoupper(substr(md5($email.$course->slug.$status->value.microtime()), 0, 14)),
            'student_id' => $student->id, 'course_id' => $course->id, 'amount' => 99000, 'status' => $status,
            'payment_url' => 'https://sandbox.doku.com/checkout-link-v2/x', 'expires_at' => now()->addHour(),
        ]);
    }

    public function test_managers_and_admins_get_the_bell_and_consultants_do_not(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $consultant = User::factory()->create(['role' => UserRole::Consultant]);
        $order = $this->order($this->course(), 'a@example.test', OrderStatus::Pending);

        app(TeamAlerts::class)->newOrder($order);

        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame(1, $manager->notifications()->count());
        $this->assertSame(0, $consultant->notifications()->count());
        $this->assertStringContainsString('Budi Santoso memesan', $admin->notifications()->first()->data['body']);
    }

    public function test_a_payment_rings_the_bell_once(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order = $this->order($this->course(), 'a@example.test', OrderStatus::Pending);

        app(EnrollmentGrant::class)->markPaid($order);
        app(EnrollmentGrant::class)->markPaid($order);

        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame('Pembayaran diterima', $admin->notifications()->first()->data['title']);
    }

    public function test_a_student_message_rings_the_bell(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $course = $this->course();
        $student = Student::create(['name' => 'Siswa', 'email' => 's@example.test']);
        $student->enrollments()->create(['course_id' => $course->id, 'source' => EnrollmentSource::Manual, 'granted_at' => now()]);

        $this->postJson('/api/learn/requests', ['kind' => 'problem', 'body' => 'Video kedua tidak bisa diputar.'], ['Authorization' => 'Bearer '.app(StudentSessions::class)->start($student)])
            ->assertCreated();

        $this->assertSame('Kendala dari siswa', $admin->notifications()->first()->data['title']);
    }

    public function test_unpaid_lists_only_buyers_who_still_lack_the_class(): void
    {
        $course = $this->course();
        $waiting = $this->order($course, 'a@example.test', OrderStatus::Pending);
        $expired = $this->order($course, 'b@example.test', OrderStatus::Expired);
        $paid = $this->order($course, 'c@example.test', OrderStatus::Paid);
        // Gave up on the first order, paid the second: nothing to follow up.
        $retried = $this->order($course, 'd@example.test', OrderStatus::Expired);
        app(EnrollmentGrant::class)->markPaid($this->order($course, 'd@example.test', OrderStatus::Pending));

        $this->assertEqualsCanonicalizing([$waiting->id, $expired->id], Order::unpaid()->pluck('id')->all());
        $this->assertSame('2', OrderResource::getNavigationBadge());

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        Livewire::test(ListOrders::class)
            ->set('activeTab', 'unpaid')
            ->assertCanSeeTableRecords([$waiting, $expired])
            ->assertCanNotSeeTableRecords([$paid, $retried]);
    }

    public function test_following_up_marks_the_order_and_prepares_the_message(): void
    {
        $course = $this->course();
        $order = $this->order($course, 'a@example.test', OrderStatus::Expired);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        Livewire::test(ListOrders::class)
            ->set('activeTab', 'unpaid')
            ->mountTableAction('followUp', $order)
            ->assertTableActionDataSet(fn (array $data) => str_contains($data['message'], 'Halo Budi')
                && str_contains($data['message'], '/checkout/?kelas=kelas-online-ac'))
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $order->refresh();
        $this->assertNotNull($order->followed_up_at);
        $this->assertSame($admin->id, $order->followed_up_by);
        $this->assertNull(OrderResource::getNavigationBadge());
    }

    public function test_an_open_payment_page_goes_in_the_follow_up(): void
    {
        $order = $this->order($this->course(), 'a@example.test', OrderStatus::Pending);
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

        Livewire::test(ListOrders::class)
            ->set('activeTab', 'unpaid')
            ->mountTableAction('followUp', $order)
            ->assertTableActionDataSet(fn (array $data) => str_contains($data['message'], $order->payment_url));
    }

    public function test_the_class_lists_its_students_with_progress_and_rating(): void
    {
        $course = $this->course();
        $grant = fn (string $email) => tap(Student::create(['name' => $email, 'email' => $email]), fn (Student $s) => $s->enrollments()->create(['course_id' => $course->id, 'source' => EnrollmentSource::Manual, 'granted_at' => now()]));
        $busy = $grant('rajin@example.test');
        $idle = $grant('belum@example.test');
        $lesson = $course->lessons()->first();
        LessonProgress::create(['student_id' => $busy->id, 'lesson_id' => $lesson->id, 'watched_percent' => 100, 'last_position_seconds' => 0, 'completed_at' => now()]);
        CourseReview::create(['student_id' => $busy->id, 'course_id' => $course->id, 'rating' => 5]);
        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]));

        $table = Livewire::test(StudentsRelationManager::class, ['ownerRecord' => $course, 'pageClass' => EditCourse::class]);
        $enrolled = $course->enrollments()->get()->keyBy('student_id');

        $table->assertCanSeeTableRecords($enrolled->values())
            ->assertTableColumnStateSet('watched_total', '50%', $enrolled[$busy->id])
            ->assertTableColumnStateSet('rating', '5 / 5', $enrolled[$busy->id])
            ->filterTable('not_started')
            ->assertCanSeeTableRecords([$enrolled[$idle->id]])
            ->assertCanNotSeeTableRecords([$enrolled[$busy->id]]);

        $this->actingAs(User::factory()->create(['role' => UserRole::Consultant]));
        $this->assertFalse(StudentsRelationManager::canViewForRecord($course, EditCourse::class));
    }

    public function test_a_drive_lesson_reaches_the_player_as_drive(): void
    {
        $course = $this->course();
        $course->lessons()->first()->update(['youtube_url' => 'https://drive.google.com/file/d/1PfYC-E3CFt20HXzr8SSabcdEFGH/view?usp=sharing']);
        $student = Student::create(['name' => 'S', 'email' => 's@example.test']);
        $student->enrollments()->create(['course_id' => $course->id, 'source' => EnrollmentSource::Manual, 'granted_at' => now()]);
        $auth = ['Authorization' => 'Bearer '.app(StudentSessions::class)->start($student)];

        $lesson = $this->getJson("/api/learn/courses/{$course->slug}", $auth)->assertOk()->json('modules.0.lessons.0');
        $this->assertSame(['drive', '1PfYC-E3CFt20HXzr8SSabcdEFGH'], [$lesson['provider'], $lesson['video']]);

        // Drive reports nothing, so the student marks it done.
        $this->postJson("/api/learn/lessons/{$lesson['id']}/progress", ['percent' => 100, 'position' => 0], $auth)
            ->assertOk()
            ->assertJsonPath('done', true);
    }
}
