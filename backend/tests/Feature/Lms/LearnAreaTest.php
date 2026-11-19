<?php

namespace Tests\Feature\Lms;

use App\Enums\CourseStatus;
use App\Enums\EnrollmentSource;
use App\Enums\OrderStatus;
use App\Mail\LoginLinkMail;
use App\Models\Course;
use App\Models\Order;
use App\Models\Student;
use App\Services\Lms\EnrollmentGrant;
use App\Services\Lms\PasswordIssuer;
use App\Services\Lms\StudentSessions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LearnAreaTest extends TestCase
{
    use RefreshDatabase;

    private function course(string $slug = 'kelas-online-ac'): Course
    {
        $course = Course::create(['title' => 'Kelas '.$slug, 'slug' => $slug, 'price' => 99000, 'status' => CourseStatus::Published]);
        $module = $course->modules()->create(['title' => 'Materi', 'sort' => 1]);

        foreach (range(1, 4) as $n) {
            $module->lessons()->create(['title' => "Video {$n}", 'sort' => $n, 'youtube_url' => 'https://youtu.be/abcdefghijk']);
        }

        return $course;
    }

    private function paidOrder(Course $course, string $email = 'pembeli@example.test', string $claim = 'claim-1'): Order
    {
        $student = Student::firstOrCreate(['email' => $email], ['name' => 'Pembeli']);
        $order = Order::create([
            'number' => 'PA'.strtoupper(substr(md5($email.$course->slug), 0, 14)), 'student_id' => $student->id, 'course_id' => $course->id,
            'amount' => 99000, 'status' => OrderStatus::Pending, 'claim_hash' => hash('sha256', $claim),
        ]);
        app(EnrollmentGrant::class)->markPaid($order);

        return $order->refresh();
    }

    private function signedIn(Student $student): array
    {
        return ['Authorization' => 'Bearer '.app(StudentSessions::class)->start($student)];
    }

    public function test_paying_mails_one_link_that_opens_the_class(): void
    {
        Mail::fake();
        $course = $this->course();
        $order = $this->paidOrder($course);
        app(EnrollmentGrant::class)->markPaid($order);

        Mail::assertSent(LoginLinkMail::class, 1);
        Mail::assertSent(LoginLinkMail::class, fn (LoginLinkMail $mail) => $mail->hasTo('pembeli@example.test')
            && $mail->course->is($course)
            && str_starts_with($mail->url, 'https://academy.pitcar.co.id/belajar/masuk/?token='));
    }

    public function test_a_reused_gateway_reference_does_not_block_a_payment(): void
    {
        Mail::fake();
        $course = $this->course();
        $first = $this->paidOrder($course, 'a@example.test');
        $first->update(['gateway_reference' => 'ref-1']);
        $student = Student::create(['name' => 'B', 'email' => 'b@example.test']);
        $second = Order::create(['number' => 'PASECOND00000001', 'student_id' => $student->id, 'course_id' => $course->id, 'amount' => 99000, 'status' => OrderStatus::Pending]);

        app(EnrollmentGrant::class)->markPaid($second, 'ref-1');

        $this->assertSame(OrderStatus::Paid, $second->refresh()->status);
        $this->assertNull($second->gateway_reference);
        $this->assertTrue($student->hasAccessTo($course));
    }

    public function test_the_buying_browser_opens_the_class_without_the_mail(): void
    {
        Mail::fake();
        $order = $this->paidOrder($this->course());

        $this->postJson("/api/checkout/orders/{$order->number}/access", ['claim' => 'wrong'])->assertStatus(403);
        $token = $this->postJson("/api/checkout/orders/{$order->number}/access", ['claim' => 'claim-1'])->assertOk()->json('token');

        $session = $this->postJson('/api/learn/session', ['token' => $token])->assertOk()->json('session');
        $this->postJson('/api/learn/session', ['token' => $token])->assertStatus(422);

        $this->getJson('/api/learn/courses', ['Authorization' => "Bearer {$session}"])
            ->assertOk()
            ->assertJsonPath('courses.0.slug', 'kelas-online-ac')
            ->assertJsonPath('courses.0.lessons', 4)
            ->assertJsonPath('courses.0.next.title', 'Video 1');
    }

    public function test_direct_access_is_refused_before_payment_and_after_the_window(): void
    {
        Mail::fake();
        $course = $this->course();
        $student = Student::create(['name' => 'A', 'email' => 'a@example.test']);
        $pending = Order::create(['number' => 'PAPENDING0000001', 'student_id' => $student->id, 'course_id' => $course->id, 'amount' => 99000, 'status' => OrderStatus::Pending, 'claim_hash' => hash('sha256', 'c')]);
        $this->postJson("/api/checkout/orders/{$pending->number}/access", ['claim' => 'c'])->assertStatus(403);

        $old = $this->paidOrder($course, 'b@example.test');
        $old->update(['paid_at' => now()->subHours(25)]);
        $this->postJson("/api/checkout/orders/{$old->number}/access", ['claim' => 'claim-1'])->assertStatus(403);
    }

    public function test_paying_with_an_email_that_already_owns_a_class_mails_a_link_instead(): void
    {
        $owner = Student::create(['name' => 'Pemilik', 'email' => 'pemilik@example.test', 'whatsapp' => '628111']);
        app(EnrollmentGrant::class)->grant($owner, $this->course('kelas-online-inspeksi'), EnrollmentSource::Manual);
        Mail::fake();

        $order = $this->paidOrder($this->course(), 'pemilik@example.test');
        $response = $this->postJson("/api/checkout/orders/{$order->number}/access", ['claim' => 'claim-1'])->assertOk();

        $response->assertJson(['emailed' => true])->assertJsonMissingPath('token');
        Mail::assertSent(LoginLinkMail::class, 2);
    }

    public function test_checkout_does_not_rewrite_an_account_that_owns_a_class(): void
    {
        $owner = Student::create(['name' => 'Pemilik', 'email' => 'pemilik@example.test', 'whatsapp' => '628111']);
        app(EnrollmentGrant::class)->grant($owner, $this->course('kelas-online-inspeksi'), EnrollmentSource::Manual);
        $this->course();
        config(['doku.client_id' => 'C', 'doku.secret_key' => 'S']);
        Http::fake(['*' => Http::response(['response' => ['payment' => ['url' => 'https://x']]])]);

        $this->postJson('/api/checkout', ['course' => 'kelas-online-ac', 'name' => 'Orang Lain', 'email' => 'pemilik@example.test', 'whatsapp' => '089999999999'])->assertCreated();

        $this->assertSame('Pemilik', $owner->refresh()->name);
        $this->assertSame('628111', $owner->whatsapp);
    }

    public function test_the_buying_browser_is_shown_a_password_once_and_can_log_in_with_it(): void
    {
        Mail::fake();
        $order = $this->paidOrder($this->course());

        $this->postJson("/api/checkout/orders/{$order->number}/password", ['claim' => 'wrong'])->assertStatus(403);
        $password = $this->postJson("/api/checkout/orders/{$order->number}/password", ['claim' => 'claim-1'])->assertOk()->json('password');

        $this->assertMatchesRegularExpression('/^[A-HJKMNP-Z2-9]{4}-[A-HJKMNP-Z2-9]{4}-[A-HJKMNP-Z2-9]{4}$/', $password);
        $this->assertNotSame($password, $order->student->refresh()->password);
        $this->postJson("/api/checkout/orders/{$order->number}/password", ['claim' => 'claim-1'])->assertOk()->assertExactJson(['password' => null, 'reason' => 'shown']);

        $this->postJson('/api/learn/login', ['email' => 'pembeli@example.test', 'password' => 'SALAH-SALAH-SALA'])->assertStatus(422);
        $this->postJson('/api/learn/login', ['email' => 'tidak.ada@example.test', 'password' => $password])->assertStatus(422)->assertJson(['message' => 'Email atau kata sandi salah.']);
        $session = $this->postJson('/api/learn/login', ['email' => 'Pembeli@Example.test', 'password' => strtolower(str_replace('-', ' ', $password))])->assertOk()->json('session');

        $this->getJson('/api/learn/courses', ['Authorization' => "Bearer {$session}"])->assertOk()->assertJsonPath('student.password', 'system');
    }

    public function test_an_existing_account_is_not_given_a_password_by_a_new_order(): void
    {
        $owner = Student::create(['name' => 'Pemilik', 'email' => 'pemilik@example.test']);
        app(EnrollmentGrant::class)->grant($owner, $this->course('kelas-online-inspeksi'), EnrollmentSource::Manual);
        Mail::fake();
        $order = $this->paidOrder($this->course(), 'pemilik@example.test');

        $this->postJson("/api/checkout/orders/{$order->number}/password", ['claim' => 'claim-1'])->assertOk()->assertExactJson(['password' => null, 'reason' => 'existing']);
        $this->assertNull($owner->refresh()->password);
    }

    public function test_a_student_replaces_the_system_password_with_their_own(): void
    {
        Mail::fake();
        $order = $this->paidOrder($this->course());
        $student = $order->student;
        $issued = $this->postJson("/api/checkout/orders/{$order->number}/password", ['claim' => 'claim-1'])->json('password');
        $here = $this->signedIn($student);
        $elsewhere = $this->signedIn($student);

        $this->postJson('/api/learn/password', ['password' => 'pendek', 'password_confirmation' => 'pendek'], $here)->assertStatus(422)->assertJsonValidationErrors('password');
        $this->postJson('/api/learn/password', ['password' => 'Bengkel Rapi 2026', 'password_confirmation' => 'beda'], $here)->assertStatus(422);
        $this->postJson('/api/learn/password', ['password' => 'Bengkel Rapi 2026', 'password_confirmation' => 'Bengkel Rapi 2026'])->assertStatus(401);
        $this->postJson('/api/learn/password', ['password' => 'Bengkel Rapi 2026', 'password_confirmation' => 'Bengkel Rapi 2026'], $here)->assertOk();

        $this->getJson('/api/learn/courses', $here)->assertOk()->assertJsonPath('student.password', 'own');
        $this->getJson('/api/learn/courses', $elsewhere)->assertStatus(401);
        $this->postJson('/api/learn/login', ['email' => 'pembeli@example.test', 'password' => $issued])->assertStatus(422);
        $this->postJson('/api/learn/login', ['email' => 'pembeli@example.test', 'password' => 'bengkel rapi 2026'])->assertStatus(422);
        $this->postJson('/api/learn/login', ['email' => 'pembeli@example.test', 'password' => 'Bengkel Rapi 2026'])->assertOk();
    }

    public function test_guessing_a_password_is_cut_off(): void
    {
        $student = Student::create(['name' => 'A', 'email' => 'a@example.test']);
        app(PasswordIssuer::class)->set($student, 'Bengkel Rapi 2026');

        foreach (range(1, 5) as $_) {
            $this->postJson('/api/learn/login', ['email' => 'a@example.test', 'password' => 'tebakan salah'])->assertStatus(422);
        }
        $this->postJson('/api/learn/login', ['email' => 'a@example.test', 'password' => 'Bengkel Rapi 2026'])->assertStatus(429);
    }

    public function test_a_login_link_is_mailed_only_to_students_and_the_answer_never_tells(): void
    {
        Mail::fake();
        $this->paidOrder($this->course());
        Mail::fake();

        $this->postJson('/api/learn/login-link', ['email' => 'Pembeli@Example.test'])->assertOk()->assertExactJson(['message' => 'OK']);
        $this->postJson('/api/learn/login-link', ['email' => 'orang@example.test'])->assertOk()->assertExactJson(['message' => 'OK']);
        $this->postJson('/api/learn/login-link', ['email' => 'salah'])->assertStatus(422);

        Mail::assertSent(LoginLinkMail::class, 1);
        Mail::assertSent(LoginLinkMail::class, fn (LoginLinkMail $mail) => $mail->course === null && $mail->minutes === 30);
    }

    public function test_the_learning_area_needs_a_session_and_logout_ends_it(): void
    {
        $student = Student::create(['name' => 'A', 'email' => 'a@example.test']);
        $headers = $this->signedIn($student);

        $this->getJson('/api/learn/courses')->assertStatus(401);
        $this->getJson('/api/learn/courses', ['Authorization' => 'Bearer salah'])->assertStatus(401);
        $this->getJson('/api/learn/courses', $headers)->assertOk()->assertJson(['courses' => []]);

        $this->deleteJson('/api/learn/session', [], $headers)->assertOk();
        $this->getJson('/api/learn/courses', $headers)->assertStatus(401);
    }

    public function test_a_course_shows_its_lessons_and_progress_only_to_its_students(): void
    {
        Mail::fake();
        $course = $this->course();
        $student = $this->paidOrder($course)->student;
        $headers = $this->signedIn($student);
        $lesson = $course->lessons()->orderBy('lessons.sort')->first();

        $this->postJson("/api/learn/lessons/{$lesson->id}/progress", ['percent' => 95, 'position' => 300], $headers)
            ->assertOk()->assertJson(['percent' => 95, 'done' => true, 'course_percent' => 24]);
        $this->postJson("/api/learn/lessons/{$lesson->id}/progress", ['percent' => 10, 'position' => 20], $headers)
            ->assertOk()->assertJson(['percent' => 95, 'done' => true]);

        $this->getJson('/api/learn/courses/kelas-online-ac', $headers)->assertOk()
            ->assertJsonPath('modules.0.lessons.0.video', 'abcdefghijk')
            ->assertJsonPath('modules.0.lessons.0.done', true)
            ->assertJsonPath('modules.0.lessons.0.position', 20)
            ->assertJsonPath('modules.0.lessons.1.percent', 0)
            ->assertJsonPath('complete_at', 90);

        $outsider = $this->signedIn(Student::create(['name' => 'B', 'email' => 'b@example.test']));
        $this->getJson('/api/learn/courses/kelas-online-ac', $outsider)->assertStatus(403);
        $this->postJson("/api/learn/lessons/{$lesson->id}/progress", ['percent' => 50, 'position' => 1], $outsider)->assertStatus(403);
        $this->getJson('/api/learn/courses/tidak-ada', $headers)->assertStatus(404);
    }

    public function test_attachments_download_only_for_students_of_the_course(): void
    {
        Mail::fake();
        Storage::fake();
        $course = $this->course();
        Storage::put('lms/modul.pdf', 'isi');
        $file = $course->attachments()->create(['name' => 'Modul AC.pdf', 'path' => 'lms/modul.pdf', 'mime' => 'application/pdf', 'size' => 3]);
        $gone = $course->attachments()->create(['name' => 'Hilang.pdf', 'path' => 'lms/hilang.pdf']);
        $headers = $this->signedIn($this->paidOrder($course)->student);

        $this->get("/api/learn/attachments/{$file->id}", $headers)->assertOk()->assertDownload('Modul AC.pdf');
        $this->getJson("/api/learn/attachments/{$gone->id}", $headers)->assertStatus(404);
        $this->getJson("/api/learn/attachments/{$file->id}", $this->signedIn(Student::create(['name' => 'B', 'email' => 'b@example.test'])))->assertStatus(403);
        $this->getJson("/api/learn/attachments/{$file->id}")->assertStatus(401);
    }
}
