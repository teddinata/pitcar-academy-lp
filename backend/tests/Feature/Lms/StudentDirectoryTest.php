<?php

namespace Tests\Feature\Lms;

use App\Enums\CourseStatus;
use App\Enums\EnrollmentSource;
use App\Enums\UserRole;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use App\Services\Lms\StudentSessions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StudentDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function course(string $slug = 'kelas-online-ac'): Course
    {
        return Course::create(['title' => 'Kelas '.$slug, 'slug' => $slug, 'price' => 99000, 'status' => CourseStatus::Published]);
    }

    private function enrol(Course $course, string $email, bool $revoked = false): Student
    {
        $student = Student::firstOrCreate(['email' => $email], ['name' => 'Siswa '.$email]);
        $student->enrollments()->create([
            'course_id' => $course->id,
            'source' => EnrollmentSource::Manual,
            'granted_at' => now(),
            'revoked_at' => $revoked ? now() : null,
        ]);

        return $student;
    }

    public function test_the_sales_page_count_holds_only_students_who_still_have_the_class(): void
    {
        $ac = $this->course();
        $inspeksi = $this->course('kelas-online-inspeksi');
        $this->enrol($ac, 'a@example.test');
        $this->enrol($ac, 'b@example.test');
        $this->enrol($ac, 'c@example.test', revoked: true);
        $this->enrol($inspeksi, 'a@example.test');

        $this->getJson('/api/checkout/courses/kelas-online-ac')->assertJsonPath('course.students', 2);
        $this->getJson('/api/checkout/courses/kelas-online-inspeksi')->assertJsonPath('course.students', 1);
    }

    public function test_only_managers_and_admins_open_the_student_list(): void
    {
        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->create(['role' => UserRole::Consultant]));
        $this->assertFalse(StudentResource::canAccess());

        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]));
        $this->assertTrue(StudentResource::canAccess());
    }

    public function test_the_list_shows_every_student_across_classes_with_their_last_login(): void
    {
        Filament::setCurrentPanel('admin');
        $ac = $this->course();
        $inspeksi = $this->course('kelas-online-inspeksi');
        $both = $this->enrol($ac, 'dua@example.test');
        $this->enrol($inspeksi, 'dua@example.test');
        $both->update(['last_login_at' => now()->subDay()]);
        app(StudentSessions::class)->start($both);
        $never = $this->enrol($inspeksi, 'belum@example.test');
        $away = $this->enrol($ac, 'lama@example.test');
        $away->update(['last_login_at' => now()->subDays(30)]);
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

        Livewire::test(ListStudents::class)
            ->assertCanSeeTableRecords([$both, $never, $away])
            ->assertSee('Kelas kelas-online-ac')
            ->assertSee('Kelas kelas-online-inspeksi')
            ->assertSee('Belum pernah login')
            ->filterTable('never_logged_in')
            ->assertCanSeeTableRecords([$never])
            ->assertCanNotSeeTableRecords([$both, $away]);

        Livewire::test(ListStudents::class)
            ->filterTable('away')
            ->assertCanSeeTableRecords([$never, $away])
            ->assertCanNotSeeTableRecords([$both]);

        Livewire::test(ListStudents::class)
            ->filterTable('course', $ac->id)
            ->assertCanSeeTableRecords([$both, $away])
            ->assertCanNotSeeTableRecords([$never]);

        Livewire::test(ListStudents::class)
            ->sortTable('last_active_at', 'desc')
            ->sortTable('last_learned_at', 'desc')
            ->assertSuccessful();
    }
}
