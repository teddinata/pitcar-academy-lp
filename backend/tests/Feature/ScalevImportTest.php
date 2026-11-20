<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\EnrollmentSource;
use App\Enums\UserRole;
use App\Filament\Resources\ScalevUnpaidOrders\Pages\ListScalevUnpaidOrders;
use App\Filament\Resources\ScalevUnpaidOrders\Tables\ScalevUnpaidOrdersTable;
use App\Jobs\SendScalevUnpaidEmail;
use App\Mail\ScalevMoveMail;
use App\Mail\ScalevUnpaidMail;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ScalevUnpaidOrder;
use App\Models\Student;
use App\Models\User;
use App\Services\Lms\ScalevUnpaidMailer;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ScalevImportTest extends TestCase
{
    use RefreshDatabase;

    private Course $ac;

    private Course $inspeksi;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.scalev.api_key' => 'test-key']);
        $this->ac = Course::create(['title' => 'Kelas AC Mobil', 'slug' => 'kelas-online-ac', 'price' => 149000, 'status' => CourseStatus::Published]);
        $this->inspeksi = Course::create(['title' => 'Kelas Inspeksi Mobil Bekas', 'slug' => 'kelas-online-inspeksi', 'price' => 99000, 'status' => CourseStatus::Published]);
    }

    private function order(string $id, array $over = []): array
    {
        return array_replace([
            'order_id' => $id,
            'created_at' => now()->subDay()->toIso8601String(),
            'confirmed_time' => now()->subDay()->toIso8601String(),
            'completed_time' => null,
            'payment_status' => 'paid',
            'status' => 'completed',
            'gross_revenue' => '99000.00',
            'final_variants' => ['Kelas Inspeksi Mobil Bekas' => 1],
            'customer' => ['name' => 'Budi Santoso', 'email' => 'Budi@Example.test', 'phone' => '0812-3456-7890'],
            'destination_address' => ['city' => 'Surabaya'],
            'is_probably_spam' => false,
        ], $over);
    }

    /**
     * Each argument is one command run, as a list of pages. Two fakes on one
     * URL merge and the first wins, so every run shares one sequence.
     */
    private function fake(array ...$runs): void
    {
        $sequence = Http::sequence();

        foreach ($runs as $pages) {
            foreach ($pages as $i => $orders) {
                $last = $i === count($pages) - 1;
                $sequence->push(['data' => $orders, 'has_next' => ! $last, 'next_cursor' => $last ? null : 'c'.$i, 'page_size' => 25]);
            }
        }

        Http::fake(['api.scalev.com/v3/orders*' => $sequence]);
    }

    public function test_paid_buyers_get_their_class_quietly_across_every_page(): void
    {
        Mail::fake();
        $this->fake([
            [$this->order('A1')],
            [$this->order('A2', ['final_variants' => ['Bundling AC Mobil + Inspeksi' => 1], 'customer' => ['name' => 'Sari', 'email' => 'sari@example.test', 'phone' => '6281111111111']])],
        ]);

        $this->artisan('scalev:import')->assertSuccessful();

        $budi = Student::where('email', 'budi@example.test')->firstOrFail();
        $this->assertSame('6281234567890', $budi->whatsapp);
        $this->assertTrue($budi->hasAccessTo($this->inspeksi));
        $this->assertFalse($budi->hasAccessTo($this->ac));
        $this->assertSame(EnrollmentSource::Scalev, $budi->enrollments()->first()->source);

        // A bundle names both classes and opens both.
        $sari = Student::where('email', 'sari@example.test')->firstOrFail();
        $this->assertTrue($sari->hasAccessTo($this->ac));
        $this->assertTrue($sari->hasAccessTo($this->inspeksi));

        Mail::assertNothingOutgoing();
    }

    public function test_dry_run_writes_nothing_and_running_twice_creates_nothing_twice(): void
    {
        $this->fake([[$this->order('A1')]], [[$this->order('A1')]], [[$this->order('A1')]]);
        $this->artisan('scalev:import --dry-run')->assertSuccessful();
        $this->assertSame(0, Student::count());

        $this->artisan('scalev:import')->assertSuccessful();
        $this->artisan('scalev:import')->assertSuccessful();
        $this->assertSame(1, Student::count());
        $this->assertSame(1, Enrollment::count());
    }

    public function test_existing_students_keep_their_account_and_revoked_access_stays_revoked(): void
    {
        $student = Student::create(['name' => 'Budi', 'email' => 'budi@example.test']);
        Enrollment::create(['student_id' => $student->id, 'course_id' => $this->inspeksi->id, 'source' => EnrollmentSource::Manual, 'granted_at' => now(), 'revoked_at' => now()]);

        $this->fake([[$this->order('A1')]]);
        $this->artisan('scalev:import')->assertSuccessful();

        $this->assertSame(1, Student::count());
        $this->assertFalse($student->fresh()->hasAccessTo($this->inspeksi));
    }

    public function test_orders_without_email_or_a_matching_class_are_reported_not_imported(): void
    {
        $this->fake([[
            $this->order('A1', ['customer' => ['name' => 'Tanpa Email', 'phone' => '0812']]),
            $this->order('A2', ['final_variants' => ['Ebook Servis Rem' => 1], 'customer' => ['name' => 'X', 'email' => 'x@example.test']]),
        ]]);

        $this->artisan('scalev:import')
            ->expectsOutputToContain('Ebook Servis Rem')
            ->assertSuccessful();

        $this->assertSame(0, Enrollment::count());
    }

    public function test_recent_unpaid_orders_go_on_the_follow_up_list_and_leave_it_when_paid(): void
    {
        $this->fake([[
            $this->order('U1', ['status' => 'pending', 'payment_status' => 'unpaid', 'gross_revenue' => '79000']),
            $this->order('U2', ['status' => 'pending', 'payment_status' => 'unpaid', 'created_at' => now()->subDays(9)->toIso8601String()]),
            $this->order('U3', ['status' => 'canceled', 'payment_status' => 'unpaid']),
        ]], [[$this->order('U1')]]);
        $this->artisan('scalev:sync-purchases')->assertSuccessful();

        $row = ScalevUnpaidOrder::findOrFail('U1');
        $this->assertSame($this->inspeksi->id, $row->course_id);
        $this->assertSame(79000, $row->amount);
        $this->assertSame(['U1'], ScalevUnpaidOrder::pluck('order_id')->all());
        $this->assertSame(0, Enrollment::count());

        // Paid on Scalev after all: access opens and the row goes.
        $this->artisan('scalev:sync-purchases')->assertSuccessful();
        $this->assertNull(ScalevUnpaidOrder::find('U1'));
        $this->assertSame(1, Enrollment::count());
    }

    public function test_the_list_hides_buyers_who_since_bought_the_class_here(): void
    {
        ScalevUnpaidOrder::create(['order_id' => 'U1', 'customer_name' => 'Budi', 'email' => 'budi@example.test', 'course_id' => $this->inspeksi->id, 'ordered_at' => now()->subDay()]);
        $this->assertSame(1, ScalevUnpaidOrder::toFollowUp()->count());

        $student = Student::create(['name' => 'Budi', 'email' => 'budi@example.test']);
        Enrollment::create(['student_id' => $student->id, 'course_id' => $this->inspeksi->id, 'source' => EnrollmentSource::Doku, 'granted_at' => now()]);
        $this->assertSame(0, ScalevUnpaidOrder::toFollowUp()->count());
    }

    public function test_the_follow_up_message_sends_to_the_new_checkout_at_todays_price(): void
    {
        config(['lms.frontend_url' => 'https://academy.example.test']);
        $row = ScalevUnpaidOrder::create(['order_id' => 'U1', 'customer_name' => 'Budi Santoso', 'course_id' => $this->ac->id, 'amount' => 99000, 'ordered_at' => now()]);

        $message = ScalevUnpaidOrdersTable::message($row->load('course'));

        $this->assertStringContainsString('Halo Budi,', $message);
        $this->assertStringContainsString('https://academy.example.test/checkout/?kelas=kelas-online-ac&utm_source=whatsapp', $message);
        $this->assertStringContainsString('Rp149.000', $message);
        $this->assertStringContainsString('Tagihan yang lama tidak perlu dibayar', $message);
    }

    public function test_managers_see_the_list_and_consultants_do_not(): void
    {
        ScalevUnpaidOrder::create(['order_id' => 'U1', 'customer_name' => 'Budi Santoso', 'email' => 'budi@example.test', 'product_name' => 'Kelas AC Mobil', 'course_id' => $this->ac->id, 'ordered_at' => now()]);

        $this->actingAs(User::factory()->create(['role' => UserRole::Consultant, 'is_active' => true]));
        $this->get('/admin/pesanan-scalev')->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => UserRole::Manager, 'is_active' => true]));
        $this->get('/admin/pesanan-scalev')->assertOk()->assertSee('Belum bayar di Scalev')->assertSee('Budi Santoso');
    }

    public function test_the_announcement_emails_each_moved_buyer_once(): void
    {
        Mail::fake();
        $this->fake([[$this->order('A1'), $this->order('A2', ['customer' => ['name' => 'Sari', 'email' => 'sari@example.test']])]]);
        $this->artisan('scalev:import')->assertSuccessful();
        // A buyer from the new checkout is not part of the move.
        $doku = Student::create(['name' => 'Baru', 'email' => 'baru@example.test']);
        $doku->enrollments()->create(['course_id' => $this->ac->id, 'source' => EnrollmentSource::Doku, 'granted_at' => now()]);

        $this->artisan('scalev:announce --dry-run')->expectsOutputToContain('2 pembeli')->assertSuccessful();
        Mail::assertNothingSent();

        $this->artisan('scalev:announce')->expectsOutputToContain('Terkirim: 2')->assertSuccessful();
        Mail::assertSent(ScalevMoveMail::class, 2);
        Mail::assertSent(ScalevMoveMail::class, fn (ScalevMoveMail $mail) => $mail->hasTo('budi@example.test')
            && $mail->titles === ['Kelas Inspeksi Mobil Bekas']
            && str_contains($mail->url, '/belajar/masuk/?token='));
        $this->assertNotNull(Student::where('email', 'budi@example.test')->value('scalev_announced_at'));

        $this->artisan('scalev:announce')->expectsOutputToContain('0 pembeli')->assertSuccessful();
        Mail::assertSent(ScalevMoveMail::class, 2);
    }

    private function unpaid(string $id, ?string $email, array $over = []): ScalevUnpaidOrder
    {
        return ScalevUnpaidOrder::create(array_replace(['order_id' => $id, 'customer_name' => 'Budi Santoso', 'email' => $email, 'course_id' => $this->ac->id, 'amount' => 99000, 'ordered_at' => now()->subDay()], $over));
    }

    public function test_the_unpaid_follow_up_email_goes_once_to_each_order_with_an_address(): void
    {
        Mail::fake();
        config(['lms.frontend_url' => 'https://academy.example.test']);
        $this->unpaid('U1', 'budi@example.test');
        $this->unpaid('U2', null);
        $this->unpaid('U3', 'lama@example.test', ['ordered_at' => now()->subDays(9)]);
        $this->unpaid('U4', 'sudah@example.test');
        $bought = Student::create(['name' => 'Sudah', 'email' => 'sudah@example.test']);
        $bought->enrollments()->create(['course_id' => $this->ac->id, 'source' => EnrollmentSource::Doku, 'granted_at' => now()]);

        $this->artisan('scalev:email-unpaid --dry-run')
            ->expectsOutputToContain('1 pesanan belum bayar belum dikirimi email')
            ->expectsOutputToContain('1 pesanan tidak punya email')
            ->assertSuccessful();
        Mail::assertNothingSent();

        $this->artisan('scalev:email-unpaid')->expectsOutputToContain('Terkirim: 1')->assertSuccessful();
        Mail::assertSent(ScalevUnpaidMail::class, 1);
        Mail::assertSent(ScalevUnpaidMail::class, function (ScalevUnpaidMail $mail) {
            $html = $mail->render();

            return $mail->hasTo('budi@example.test')
                && str_contains($html, 'Halo Budi,')
                && str_contains($html, 'https://academy.example.test/checkout/?kelas=kelas-online-ac&amp;utm_source=email')
                && str_contains($html, 'Rp149.000')
                && str_contains($html, 'Kalau sudah tidak berminat');
        });
        $this->assertNotNull(ScalevUnpaidOrder::find('U1')->emailed_at);

        $this->artisan('scalev:email-unpaid')->expectsOutputToContain('0 pesanan')->assertSuccessful();
        Mail::assertSent(ScalevUnpaidMail::class, 1);
    }

    public function test_the_admin_queues_the_follow_up_email_for_all_or_for_picked_orders(): void
    {
        Filament::setCurrentPanel('admin');
        Queue::fake();
        $this->actingAs(User::factory()->create(['role' => UserRole::Manager, 'is_active' => true]));
        $a = $this->unpaid('U1', 'a@example.test');
        $b = $this->unpaid('U2', 'b@example.test');
        $none = $this->unpaid('U3', null);

        Livewire::test(ListScalevUnpaidOrders::class)
            ->callTableBulkAction('email', [$a, $none])
            ->assertNotified('1 email masuk antrean');
        Queue::assertPushed(SendScalevUnpaidEmail::class, 1);

        Livewire::test(ListScalevUnpaidOrders::class)->mountAction('previewEmail')->assertActionMounted('previewEmail')->assertHasNoErrors();
        Livewire::test(ListScalevUnpaidOrders::class)->callAction('emailAll');
        Queue::assertPushed(SendScalevUnpaidEmail::class, 3);

        Mail::fake();
        (new SendScalevUnpaidEmail('U2'))->handle(app(ScalevUnpaidMailer::class));
        Mail::assertSent(ScalevUnpaidMail::class, fn ($mail) => $mail->hasTo('b@example.test'));
        // A second job for the same order sends nothing.
        (new SendScalevUnpaidEmail('U2'))->handle(app(ScalevUnpaidMailer::class));
        Mail::assertSent(ScalevUnpaidMail::class, 1);
    }
}
