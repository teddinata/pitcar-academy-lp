<?php

namespace Tests\Feature\Lms;

use App\Enums\CourseStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\AccessDelivery;
use App\Models\Course;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Services\Lms\AccessWhatsApp;
use App\Services\Lms\EnrollmentGrant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class WhatsAppAccessTest extends TestCase
{
    use RefreshDatabase;

    private const SEND_URL = 'api.kirimdev.com/v1/PNID-1/messages';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'kirimdev.api_key' => 'kd-test',
            'kirimdev.phone_number_id' => 'PNID-1',
            'kirimdev.access_auto' => true,
            'lms.frontend_url' => 'https://academy.test',
        ]);
    }

    private function order(string $whatsapp = '6281234567890', OrderStatus $status = OrderStatus::Pending): Order
    {
        $course = Course::firstOrCreate(['slug' => 'kelas-online-ac'], ['title' => 'Kelas AC Mobil', 'price' => 99000, 'status' => CourseStatus::Published]);
        $student = Student::create(['name' => 'Budi Santoso', 'email' => 'budi'.Student::count().'@example.test', 'whatsapp' => $whatsapp]);

        return Order::create([
            'number' => 'PA-'.uniqid(),
            'student_id' => $student->id,
            'course_id' => $course->id,
            'amount' => 99000,
            'status' => $status,
            'gateway' => 'doku',
        ]);
    }

    public function test_payment_sends_the_access_template_once(): void
    {
        Http::fake([self::SEND_URL => Http::response(['data' => ['id' => 'msg-1', 'status' => 'accepted']], 202)]);
        $order = $this->order();

        app(EnrollmentGrant::class)->markPaid($order);
        // DOKU repeats notifications; the second one must not send another message.
        app(EnrollmentGrant::class)->markPaid($order->refresh());

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            $params = collect($request['template']['components'][0]['parameters'])->pluck('text')->all();

            return $request->hasHeader('Authorization', 'Bearer kd-test')
                && $request['to'] === '+6281234567890'
                && $request['template']['name'] === 'academy_akses_kelas'
                && $params[0] === 'Budi'
                && $params[1] === 'Kelas AC Mobil'
                && str_starts_with($params[2], 'https://academy.test/belajar/masuk/?token=')
                && $params[3] === '72 jam';
        });

        $delivery = AccessDelivery::sole();
        $this->assertSame('sent', $delivery->status);
        $this->assertSame('auto', $delivery->mode);
        $this->assertSame('msg-1', $delivery->provider_message_id);
    }

    public function test_without_kirimdev_settings_the_payment_still_goes_through(): void
    {
        config(['kirimdev.api_key' => null]);
        Http::fake();
        $order = $this->order();

        app(EnrollmentGrant::class)->markPaid($order);

        Http::assertNothingSent();
        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
        $this->assertSame('skipped', AccessDelivery::sole()->status);
        $this->assertStringContainsString('kirimdev belum diatur', AccessDelivery::sole()->last_error);
    }

    public function test_an_invalid_number_is_skipped(): void
    {
        Http::fake();

        app(EnrollmentGrant::class)->markPaid($this->order('12345'));

        Http::assertNothingSent();
        $this->assertSame('skipped', AccessDelivery::sole()->status);
    }

    public function test_auto_send_can_be_switched_off(): void
    {
        config(['kirimdev.access_auto' => false]);
        Http::fake();

        app(EnrollmentGrant::class)->markPaid($this->order());

        Http::assertNothingSent();
        $this->assertSame(0, AccessDelivery::count());
    }

    public function test_a_kirimdev_outage_is_retried_by_the_scheduler(): void
    {
        Http::fakeSequence(self::SEND_URL)
            ->push(['error' => ['code' => 'upstream_error', 'message' => 'down']], 503)
            ->push(['data' => ['id' => 'msg-2', 'status' => 'accepted']], 202);

        app(EnrollmentGrant::class)->markPaid($this->order());
        $delivery = AccessDelivery::sole();
        $this->assertSame('queued', $delivery->status);
        $this->assertSame(1, $delivery->attempts);

        $this->travel(2)->minutes();
        $this->artisan('lms:send-whatsapp-access')->assertSuccessful();

        $delivery->refresh();
        $this->assertSame('sent', $delivery->status);
        $this->assertSame(2, $delivery->attempts);
    }

    public function test_a_rejected_template_fails_without_retrying(): void
    {
        Http::fake([self::SEND_URL => Http::response(['error' => ['code' => 'template_not_found', 'message' => 'no such template']], 422)]);

        app(EnrollmentGrant::class)->markPaid($this->order());
        $this->travel(2)->minutes();
        $this->artisan('lms:send-whatsapp-access');

        Http::assertSentCount(1);
        $this->assertSame('failed', AccessDelivery::sole()->status);
        $this->assertStringContainsString('template_not_found', AccessDelivery::sole()->last_error);
    }

    public function test_manual_send_uses_the_corrected_number(): void
    {
        Http::fake([self::SEND_URL => Http::response(['data' => ['id' => 'msg-3', 'status' => 'accepted']], 202)]);
        $order = $this->order('6281200000000', OrderStatus::Paid);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $delivery = app(AccessWhatsApp::class)->sendManually($order, $admin, '0813 9999 8888');

        $this->assertSame('sent', $delivery->status);
        $this->assertSame('manual', $delivery->mode);
        $this->assertSame($admin->id, $delivery->user_id);
        Http::assertSent(fn (Request $request) => $request['to'] === '+6281399998888');
    }

    public function test_wa_me_link_carries_a_fresh_login_link(): void
    {
        $order = $this->order('081234567890', OrderStatus::Paid);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $url = app(AccessWhatsApp::class)->waMeLink($order, $admin);

        $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $url);
        $text = rawurldecode(substr($url, strpos($url, '=') + 1));
        $this->assertStringContainsString('Halo Budi', $text);
        $this->assertStringContainsString('https://academy.test/belajar/masuk/?token=', $text);
        $this->assertSame('opened', AccessDelivery::sole()->status);
    }

    public function test_only_managers_and_admins_see_orders(): void
    {
        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->create(['role' => UserRole::Consultant]));
        $this->assertFalse(OrderResource::canAccess());

        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]));
        $this->assertTrue(OrderResource::canAccess());
    }

    public function test_the_panel_sends_access_from_the_order_list(): void
    {
        Filament::setCurrentPanel('admin');
        Http::fake([self::SEND_URL => Http::response(['data' => ['id' => 'msg-4', 'status' => 'accepted']], 202)]);
        $order = $this->order('6281200000000', OrderStatus::Paid);
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

        Livewire::test(ListOrders::class)
            ->assertCanSeeTableRecords([$order])
            ->callTableAction('sendWhatsApp', $order, ['phone' => '081377776666', 'save_phone' => true])
            ->assertHasNoTableActionErrors();

        $this->assertSame('6281377776666', $order->student->refresh()->whatsapp);
        $this->assertSame('sent', AccessDelivery::sole()->status);
    }
}
