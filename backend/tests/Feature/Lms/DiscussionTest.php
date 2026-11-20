<?php

namespace Tests\Feature\Lms;

use App\Enums\CourseStatus;
use App\Enums\EnrollmentSource;
use App\Enums\UserRole;
use App\Filament\Resources\Discussions\DiscussionResource;
use App\Filament\Resources\Discussions\Pages\ListDiscussions;
use App\Filament\Resources\Discussions\Pages\ViewDiscussion;
use App\Filament\Resources\Discussions\RelationManagers\RepliesRelationManager;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\DiscussionPost;
use App\Models\Lesson;
use App\Models\Student;
use App\Models\User;
use App\Services\Lms\StudentSessions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DiscussionTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();
        $this->course = Course::create(['title' => 'Kelas AC Mobil', 'slug' => 'kelas-online-ac', 'price' => 99000, 'status' => CourseStatus::Published]);
        $module = CourseModule::create(['course_id' => $this->course->id, 'title' => 'Dasar', 'sort' => 1]);
        $this->lesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'Cara kerja kompresor', 'sort' => 1]);
    }

    private function student(string $email = 'budi@example.test', string $name = 'Budi Santoso', bool $enrolled = true): Student
    {
        $student = Student::create(['name' => $name, 'email' => $email]);
        if ($enrolled) {
            $student->enrollments()->create(['course_id' => $this->course->id, 'source' => EnrollmentSource::Manual, 'granted_at' => now()]);
        }

        return $student;
    }

    private function as(Student $student): array
    {
        return ['Authorization' => 'Bearer '.app(StudentSessions::class)->start($student)];
    }

    private function admin(UserRole $role = UserRole::Admin): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    public function test_a_student_asks_and_classmates_see_it_with_a_masked_name(): void
    {
        $budi = $this->student();
        $sari = $this->student('sari@example.test', 'Sari Dewi');

        $this->postJson('/api/learn/courses/kelas-online-ac/discussions', ['body' => 'Kenapa kompresor bunyi kasar?', 'lesson_id' => $this->lesson->id], $this->as($budi))
            ->assertCreated()
            ->assertJsonPath('thread.author', 'Budi S.')
            ->assertJsonPath('thread.mine', true)
            ->assertJsonPath('thread.lesson.title', 'Cara kerja kompresor');

        $this->getJson('/api/learn/courses/kelas-online-ac/discussions?lesson='.$this->lesson->id, $this->as($sari))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('class_total', 1)
            ->assertJsonPath('threads.0.body', 'Kenapa kompresor bunyi kasar?')
            ->assertJsonPath('threads.0.mine', false)
            ->assertJsonMissingPath('threads.0.email');
    }

    public function test_only_students_who_own_the_class_can_read_or_post(): void
    {
        $outsider = $this->student('luar@example.test', 'Orang Luar', enrolled: false);

        $this->getJson('/api/learn/courses/kelas-online-ac/discussions', $this->as($outsider))->assertForbidden();
        $this->postJson('/api/learn/courses/kelas-online-ac/discussions', ['body' => 'Halo semua'], $this->as($outsider))->assertForbidden();
        $this->getJson('/api/learn/courses/kelas-online-ac/discussions')->assertUnauthorized();
    }

    public function test_replies_form_a_thread_and_can_answer_a_reply(): void
    {
        $budi = $this->student();
        $sari = $this->student('sari@example.test', 'Sari Dewi');
        $thread = DiscussionPost::create(['course_id' => $this->course->id, 'student_id' => $budi->id, 'body' => 'Pertanyaan', 'last_activity_at' => now()->subDay()]);

        $first = $this->postJson("/api/learn/discussions/{$thread->id}/replies", ['body' => 'Coba cek oli kompresor.'], $this->as($sari))
            ->assertCreated()->json('reply.id');
        $this->postJson("/api/learn/discussions/{$thread->id}/replies", ['body' => 'Sudah, masih bunyi.', 'reply_to_id' => $first], $this->as($budi))
            ->assertCreated()
            ->assertJsonPath('reply.reply_to.author', 'Sari D.');

        $this->getJson("/api/learn/discussions/{$thread->id}", $this->as($budi))
            ->assertOk()
            ->assertJsonCount(2, 'replies')
            ->assertJsonPath('thread.reply_count', 2);
        $this->assertTrue($thread->fresh()->last_activity_at->isToday());
    }

    public function test_a_student_deletes_only_their_own_message(): void
    {
        $budi = $this->student();
        $sari = $this->student('sari@example.test', 'Sari Dewi');
        $post = DiscussionPost::create(['course_id' => $this->course->id, 'student_id' => $budi->id, 'body' => 'Salah tulis', 'last_activity_at' => now()]);

        $this->postJson("/api/learn/discussions/{$post->id}/delete", [], $this->as($sari))->assertForbidden();
        $this->postJson("/api/learn/discussions/{$post->id}/delete", [], $this->as($budi))->assertOk();

        $this->assertSame(DiscussionPost::REASON_AUTHOR, $post->fresh()->delete_reason);
        $this->getJson('/api/learn/courses/kelas-online-ac/discussions', $this->as($sari))->assertJsonPath('total', 0);
    }

    public function test_a_report_alerts_the_team_once_and_the_team_removes_the_message(): void
    {
        Filament::setCurrentPanel('admin');
        $admin = $this->admin();
        $budi = $this->student();
        $sari = $this->student('sari@example.test', 'Sari Dewi');
        $thread = DiscussionPost::create(['course_id' => $this->course->id, 'student_id' => $budi->id, 'body' => 'Pertanyaan', 'last_activity_at' => now()]);
        $bad = $thread->replyAsTeam($admin, 'placeholder');
        $bad->update(['user_id' => null, 'student_id' => $sari->id, 'body' => 'Kalimat kasar']);
        $thread->recount();

        $this->postJson("/api/learn/discussions/{$bad->id}/report", ['reason' => 'Menghina'], $this->as($budi))->assertOk();
        $this->postJson("/api/learn/discussions/{$bad->id}/report", [], $this->as($budi))->assertOk();
        $this->postJson("/api/learn/discussions/{$bad->id}/report", [], $this->as($sari))->assertForbidden();

        $this->assertSame(1, $admin->notifications()->count());
        $this->actingAs($admin);
        $this->assertSame('1', DiscussionResource::getNavigationBadge());
        $this->assertSame('danger', DiscussionResource::getNavigationBadgeColor());

        Livewire::test(RepliesRelationManager::class, ['ownerRecord' => $thread, 'pageClass' => ViewDiscussion::class])
            ->callTableAction('remove', $bad, ['reason' => 'abuse'])
            ->assertHasNoTableActionErrors();

        $bad->refresh();
        $this->assertSame('abuse', $bad->delete_reason);
        $this->assertSame($admin->id, $bad->deleted_by);
        $this->assertSame(0, $bad->reports()->whereNull('resolved_at')->count());
        $this->assertSame(0, $thread->fresh()->reply_count);
        $this->getJson("/api/learn/discussions/{$thread->id}", $this->as($budi))->assertJsonCount(0, 'replies');
    }

    public function test_the_team_answers_from_the_admin_and_the_student_sees_the_team_mark(): void
    {
        Filament::setCurrentPanel('admin');
        $admin = User::factory()->create(['role' => UserRole::Manager, 'is_active' => true, 'name' => 'Rina Pitcar']);
        $budi = $this->student();
        $thread = DiscussionPost::create(['course_id' => $this->course->id, 'student_id' => $budi->id, 'body' => 'Pertanyaan', 'last_activity_at' => now()]);
        $this->actingAs($admin);

        Livewire::test(ListDiscussions::class)->assertCanSeeTableRecords([$thread]);
        Livewire::test(ViewDiscussion::class, ['record' => $thread->id])
            ->callAction('teamReply', ['body' => 'Cek tekanan di sisi rendah dulu.'])
            ->assertHasNoActionErrors();

        $this->getJson("/api/learn/discussions/{$thread->id}", $this->as($budi))
            ->assertJsonPath('replies.0.team', true)
            ->assertJsonPath('replies.0.author', 'Rina');
        $this->getJson('/api/learn/courses/kelas-online-ac/discussions', $this->as($budi))->assertJsonPath('threads.0.answered', true);
        $this->assertNull(DiscussionResource::getNavigationBadge());
    }

    public function test_consultants_do_not_see_the_discussion_admin(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->admin(UserRole::Consultant));
        $this->assertFalse(DiscussionResource::canAccess());
        $this->get('/admin/diskusi')->assertForbidden();
    }

    public function test_a_post_is_checked_before_it_is_saved(): void
    {
        $this->postJson('/api/learn/courses/kelas-online-ac/discussions', ['body' => ' '], $this->as($this->student()))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body' => 'Tulis pesanmu dulu.']);
    }
}
