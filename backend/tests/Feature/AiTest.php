<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\EnrollmentSource;
use App\Enums\UserRole;
use App\Filament\Pages\AiAssistant;
use App\Jobs\AnswerAdminQuestion;
use App\Models\AiMessage;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\LessonAttachment;
use App\Models\McpToken;
use App\Models\Student;
use App\Models\User;
use App\Services\Ai\AdminAssistant;
use App\Services\Ai\BusinessTools;
use App\Services\Ai\Claude;
use App\Services\Ai\Reply;
use App\Services\Lms\StudentSessions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class AiTest extends TestCase
{
    use RefreshDatabase;

    private FakeClaude $claude;

    private Course $course;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();
        $this->claude = new FakeClaude;
        $this->app->instance(Claude::class, $this->claude);
        config(['services.anthropic.student_daily_limit' => 2]);

        $this->course = Course::create(['title' => 'Kelas AC Mobil', 'slug' => 'kelas-online-ac', 'price' => 149000, 'status' => CourseStatus::Published]);
        $module = CourseModule::create(['course_id' => $this->course->id, 'title' => 'Dasar', 'sort' => 1]);
        $this->lesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'Cara kerja kompresor', 'summary' => 'Siklus refrigeran.', 'sort' => 1]);
        LessonAttachment::create(['course_id' => $this->course->id, 'name' => 'Modul AC.pdf', 'path' => 'x.pdf', 'mime' => 'application/pdf', 'size' => 100]);
    }

    private function student(bool $enrolled = true): Student
    {
        $student = Student::create(['name' => 'Budi', 'email' => 'budi'.uniqid().'@example.test']);
        if ($enrolled) {
            $student->enrollments()->create(['course_id' => $this->course->id, 'source' => EnrollmentSource::Manual, 'granted_at' => now()]);
        }

        return $student;
    }

    private function as(Student $student): array
    {
        return ['Authorization' => 'Bearer '.app(StudentSessions::class)->start($student)];
    }

    public function test_a_student_gets_a_streamed_answer_grounded_on_the_class(): void
    {
        $budi = $this->student();

        $this->getJson('/api/learn/courses/kelas-online-ac/ai', $this->as($budi))
            ->assertOk()->assertJson(['enabled' => true, 'limit' => 2, 'remaining' => 2, 'messages' => []]);

        $stream = $this->post('/api/learn/courses/kelas-online-ac/ai', ['question' => 'Kenapa freon harus divakum?', 'lesson_id' => $this->lesson->id], $this->as($budi))
            ->assertOk()->streamedContent();

        $this->assertStringContainsString("event: delta\ndata: {\"text\":\"Karena \"}", $stream);
        $this->assertStringContainsString('"remaining":1', $stream);

        // The outline is in the system prompt; the PDF rides on the first question only.
        $first = $this->claude->calls[0];
        $this->assertStringContainsString('Cara kerja kompresor: Siklus refrigeran.', $first['system'][1]['text']);
        $this->assertSame('file_test', $first['messages'][0]['content'][0]['source']['fileID']);
        $this->assertStringContainsString('[Siswa sedang membuka video: Cara kerja kompresor]', $first['messages'][0]['content'][1]['text']);

        $this->post('/api/learn/courses/kelas-online-ac/ai', ['question' => 'Berapa lama vakumnya?'], $this->as($budi))->assertOk()->streamedContent();
        $second = $this->claude->calls[1];
        $this->assertSame($first['messages'][0], $second['messages'][0], 'history replays byte for byte, so the cache prefix holds');
        $this->assertSame('Karena udara dan air merusak sistem.', $second['messages'][1]['content']);
        $this->assertSame('Berapa lama vakumnya?', $second['messages'][2]['content']);

        $this->getJson('/api/learn/courses/kelas-online-ac/ai', $this->as($budi))
            ->assertJsonPath('remaining', 0)
            ->assertJsonPath('messages.0.text', 'Kenapa freon harus divakum?')
            ->assertJsonCount(4, 'messages');

        $this->postJson('/api/learn/courses/kelas-online-ac/ai', ['question' => 'Satu lagi?'], $this->as($budi))->assertStatus(429);
    }

    public function test_a_failed_answer_does_not_use_up_the_daily_limit(): void
    {
        $budi = $this->student();
        $this->claude->fail = true;

        $stream = $this->post('/api/learn/courses/kelas-online-ac/ai', ['question' => 'Halo AI?'], $this->as($budi))->streamedContent();

        $this->assertStringContainsString('event: error', $stream);
        $this->assertSame(0, AiMessage::count());
        $this->getJson('/api/learn/courses/kelas-online-ac/ai', $this->as($budi))->assertJsonPath('remaining', 2);
    }

    public function test_the_ai_needs_the_class_and_a_key(): void
    {
        $this->postJson('/api/learn/courses/kelas-online-ac/ai', ['question' => 'Halo AI?'], $this->as($this->student(enrolled: false)))->assertForbidden();

        $this->claude->on = false;
        $this->postJson('/api/learn/courses/kelas-online-ac/ai', ['question' => 'Halo AI?'], $this->as($this->student()))
            ->assertStatus(503)->assertJsonPath('message', 'AI Pitcar Academy belum aktif. Tanyakan di Diskusi kelas dulu, ya.');
    }

    public function test_a_new_conversation_starts_empty(): void
    {
        $budi = $this->student();
        $this->post('/api/learn/courses/kelas-online-ac/ai', ['question' => 'Halo AI?'], $this->as($budi))->streamedContent();
        $this->postJson('/api/learn/courses/kelas-online-ac/ai/new', [], $this->as($budi))->assertOk();

        $this->getJson('/api/learn/courses/kelas-online-ac/ai', $this->as($budi))->assertJsonCount(0, 'messages')->assertJsonPath('remaining', 1);
    }

    public function test_the_admin_assistant_answers_on_the_queue_with_tools(): void
    {
        Filament::setCurrentPanel('admin');
        Queue::fake();
        $this->actingAs(User::factory()->create(['role' => UserRole::Manager, 'is_active' => true]));

        $page = Livewire::test(AiAssistant::class)->set('question', 'Berapa kelas yang ada?')->call('ask')->assertHasNoErrors();
        Queue::assertPushed(AnswerAdminQuestion::class);

        $pending = AiMessage::where('status', AiMessage::PENDING)->firstOrFail();
        (new AnswerAdminQuestion($pending->id))->handle(app(AdminAssistant::class));

        $this->assertSame(AiMessage::DONE, $pending->fresh()->status);
        $this->assertSame('Ada 1 kelas: Kelas AC Mobil.', $pending->fresh()->content);
        $this->assertSame('courses', $this->claude->toolCalls[0][0]);
        $this->assertStringContainsString('"slug":"kelas-online-ac"', $this->claude->toolCalls[0][1]);
        $page->call('$refresh')->assertSee('Ada 1 kelas: Kelas AC Mobil.');
    }

    public function test_consultants_cannot_open_the_admin_assistant(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => UserRole::Consultant, 'is_active' => true]));
        $this->get('/admin/ai-pitcar-academy')->assertForbidden();
    }

    public function test_every_business_tool_returns_json(): void
    {
        $this->student();
        $tools = app(BusinessTools::class);
        $inputs = [
            'courses' => [], 'sales_summary' => [], 'traffic' => ['from' => '2026-11-01', 'to' => '2026-11-20'],
            'orders' => ['status' => 'unpaid'], 'find_student' => ['query' => 'budi'], 'course_students' => ['course' => 'kelas-online-ac', 'filter' => 'not_started'],
            'discussions' => ['filter' => 'recent'], 'student_feedback' => ['kind' => 'reviews'],
        ];

        foreach ($tools->definitions() as $def) {
            $json = json_decode($tools->run($def['name'], $inputs[$def['name']]), true);
            $this->assertIsArray($json, $def['name']);
        }

        $this->assertCount(1, json_decode($tools->run('course_students', ['course' => 'AC', 'filter' => 'not_started']), true)['students']);
    }

    public function test_the_mcp_server_needs_a_token_and_lists_read_only_tools(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $token = McpToken::issue($admin, 'Laptop');
        $rpc = fn (string $method, array $params = []) => ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params];

        $this->postJson('/mcp', $rpc('tools/list'))->assertUnauthorized();

        $list = $this->postJson('/mcp', $rpc('tools/list'), ['Authorization' => "Bearer {$token}"])->assertOk()->json('result.tools');
        $this->assertCount(8, $list);
        $this->assertTrue(collect($list)->every(fn ($t) => $t['annotations']['readOnlyHint'] === true));
        $this->assertContains('sales_summary', array_column($list, 'name'));

        $call = $this->postJson('/mcp', $rpc('tools/call', ['name' => 'courses', 'arguments' => (object) []]), ['Authorization' => "Bearer {$token}"])->assertOk();
        $this->assertStringContainsString('kelas-online-ac', $call->json('result.content.0.text'));
        $this->assertNotNull(McpToken::first()->last_used_at);

        $admin->forceFill(['is_active' => false])->save();
        $this->postJson('/mcp', $rpc('tools/list'), ['Authorization' => "Bearer {$token}"])->assertUnauthorized();
    }
}

/** Stands in for the Claude API: fixed answers, records what it was sent. */
class FakeClaude extends Claude
{
    public bool $on = true;

    public bool $fail = false;

    public array $calls = [];

    public array $toolCalls = [];

    public function configured(): bool
    {
        return $this->on;
    }

    public function stream(array $system, array $messages, callable $onText, string $effort = 'medium'): Reply
    {
        $this->calls[] = compact('system', 'messages');
        if ($this->fail) {
            throw new RuntimeException('overloaded');
        }
        foreach (['Karena ', 'udara dan air ', 'merusak sistem.'] as $piece) {
            $onText($piece);
        }
        $reply = new Reply;
        $reply->text = 'Karena udara dan air merusak sistem.';
        $reply->inputTokens = 1200;
        $reply->outputTokens = 40;

        return $reply;
    }

    public function runTools(array $system, array $messages, array $tools, callable $runTool, int $maxRounds = 8): Reply
    {
        $this->toolCalls[] = ['courses', $runTool('courses', [])];
        $reply = new Reply;
        $reply->text = 'Ada 1 kelas: Kelas AC Mobil.';

        return $reply;
    }

    public function fileId(LessonAttachment $attachment): ?string
    {
        return 'file_test';
    }
}
