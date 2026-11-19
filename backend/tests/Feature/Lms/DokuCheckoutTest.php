<?php

namespace Tests\Feature\Lms;

use App\Enums\CourseStatus;
use App\Enums\EnrollmentSource;
use App\Enums\OrderStatus;
use App\Models\Course;
use App\Models\Order;
use App\Models\Student;
use App\Services\Doku\Signature;
use App\Services\Lms\EnrollmentGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DokuCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'SK-test-secret';

    private const CLIENT = 'BRN-0000-TEST';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'doku.client_id' => self::CLIENT,
            'doku.secret_key' => self::SECRET,
            'doku.base_url' => 'https://api-sandbox.doku.com',
            'app.url' => 'https://api-academy.test',
        ]);
    }

    private function course(): Course
    {
        return Course::create(['title' => 'Kelas AC Mobil', 'slug' => 'kelas-ac-mobil', 'price' => 99000, 'status' => CourseStatus::Published]);
    }

    private function fakeDoku(): void
    {
        Http::fake([
            'api-sandbox.doku.com/checkout/v1/payment' => Http::response(['message' => ['SUCCESS'], 'response' => ['payment' => [
                'url' => 'https://sandbox.doku.com/checkout-link-v2/abc',
                'token_id' => 'abc',
                'expired_date' => now('Asia/Jakarta')->addHour()->format('YmdHis'),
            ]]]),
        ]);
    }

    private function buyer(array $over = []): array
    {
        return $over + ['course' => 'kelas-ac-mobil', 'name' => 'Pembeli', 'email' => 'Pembeli@Example.test', 'whatsapp' => '0812-3456-7890'];
    }

    private function notify(array $body, ?string $secret = null)
    {
        $json = json_encode($body);
        $id = 'req-1';
        $time = '2026-11-14T02:00:00Z';

        return $this->call('POST', '/api/payments/doku/notification', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_CLIENT_ID' => self::CLIENT,
            'HTTP_REQUEST_ID' => $id,
            'HTTP_REQUEST_TIMESTAMP' => $time,
            'HTTP_SIGNATURE' => Signature::make(self::CLIENT, $id, $time, '/api/payments/doku/notification', $json, $secret ?? self::SECRET),
        ], $json);
    }

    public function test_signature_matches_dokus_recipe(): void
    {
        $body = '{"order":{"amount":20000}}';
        $expected = 'HMACSHA256='.base64_encode(hash_hmac('sha256', implode("\n", [
            'Client-Id:C', 'Request-Id:R', 'Request-Timestamp:T', 'Request-Target:/checkout/v1/payment',
            'Digest:'.base64_encode(hash('sha256', $body, true)),
        ]), 'S', true));

        $this->assertSame($expected, Signature::make('C', 'R', 'T', '/checkout/v1/payment', $body, 'S'));
        $this->assertNotSame($expected, Signature::make('C', 'R', 'T', '/checkout/v1/payment', null, 'S'));
    }

    public function test_checkout_creates_an_order_and_a_signed_payment_request(): void
    {
        $this->course()->update(['title' => 'Kelas AC & Perawatan (Dasar)']);
        $this->fakeDoku();

        $response = $this->postJson('/api/checkout', $this->buyer(['name' => 'Bu Sri "Ayu"']))->assertCreated();

        $order = Order::firstOrFail();
        $response->assertJson(['order' => $order->number, 'status' => 'pending', 'amount' => 99000, 'payment_url' => 'https://sandbox.doku.com/checkout-link-v2/abc']);
        $this->assertMatchesRegularExpression('/^PA\d{6}[A-Z0-9]{8}$/', $order->number);
        $this->assertTrue($order->expires_at->isFuture());
        $this->assertSame('pembeli@example.test', $order->student->email);
        $this->assertSame('6281234567890', $order->student->whatsapp);

        Http::assertSent(function (Request $request) use ($order) {
            $expected = Signature::make(self::CLIENT, $request->header('Request-Id')[0], $request->header('Request-Timestamp')[0], '/checkout/v1/payment', $request->body(), self::SECRET);

            return $request->header('Signature')[0] === $expected
                && $request['order']['invoice_number'] === $order->number
                && $request['order']['amount'] === 99000
                && $request['order']['line_items'][0]['name'] === 'Kelas AC dan Perawatan (Dasar)'
                && $request['customer']['name'] === 'Bu Sri Ayu'
                && $request['additional_info']['override_notification_url'] === 'https://api-academy.test/api/payments/doku/notification';
        });
    }

    public function test_resubmitting_reuses_the_order_only_for_the_browser_that_made_it(): void
    {
        $this->course();
        $this->fakeDoku();

        $first = $this->postJson('/api/checkout', $this->buyer())->json();
        $same = $this->postJson('/api/checkout', $this->buyer(['order' => $first['order'], 'claim' => $first['claim']]))->json();
        $stranger = $this->postJson('/api/checkout', $this->buyer(['order' => $first['order'], 'claim' => 'guess']))->json();

        $this->assertSame($first['order'], $same['order']);
        $this->assertNotSame($first['order'], $stranger['order']);
        $this->assertSame(2, Order::count());
        $this->assertSame(1, Student::count());
    }

    public function test_a_buyer_who_already_has_access_is_sent_to_login(): void
    {
        $course = $this->course();
        $student = Student::create(['name' => 'Pembeli', 'email' => 'pembeli@example.test']);
        app(EnrollmentGrant::class)->grant($student, $course, EnrollmentSource::Manual);
        Http::fake();

        $this->postJson('/api/checkout', $this->buyer())->assertStatus(409)->assertJson(['code' => 'already_enrolled']);

        $this->assertSame(0, Order::count());
        Http::assertNothingSent();
    }

    public function test_checkout_rejects_bad_input_unknown_courses_and_missing_credentials(): void
    {
        $this->course();
        Http::fake();

        $this->postJson('/api/checkout', $this->buyer(['email' => 'bukan-email', 'whatsapp' => '12']))
            ->assertStatus(422)->assertJsonValidationErrors(['email', 'whatsapp']);
        $this->postJson('/api/checkout', $this->buyer(['course' => 'tidak-ada']))->assertStatus(404);

        config(['doku.secret_key' => null]);
        $this->postJson('/api/checkout', $this->buyer())->assertStatus(503);

        Http::assertNothingSent();
    }

    public function test_a_refusal_from_doku_is_reported_without_a_payment_url(): void
    {
        $this->course();
        Http::fake(['*' => Http::response(['error_messages' => ['INVALID SIGNATURE']], 400)]);

        $this->postJson('/api/checkout', $this->buyer())->assertStatus(502);

        $order = Order::firstOrFail();
        $this->assertNull($order->payment_url);
        $this->assertSame(OrderStatus::Failed, $order->status);
    }

    public function test_a_success_notification_opens_the_course_once(): void
    {
        $course = $this->course();
        $this->fakeDoku();
        $number = $this->postJson('/api/checkout', $this->buyer())->json('order');
        $body = ['order' => ['invoice_number' => $number, 'amount' => 99000], 'transaction' => ['status' => 'SUCCESS', 'original_request_id' => 'orig-1']];

        $this->notify($body)->assertOk();
        $this->notify($body)->assertOk();

        $order = Order::firstOrFail();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('orig-1', $order->gateway_reference);
        $this->assertTrue($order->student->hasAccessTo($course));
        $this->assertSame(1, $order->student->enrollments()->count());
    }

    public function test_notifications_that_are_forged_wrong_or_not_final_open_nothing(): void
    {
        $course = $this->course();
        $this->fakeDoku();
        $number = $this->postJson('/api/checkout', $this->buyer())->json('order');
        $paid = ['order' => ['invoice_number' => $number, 'amount' => 99000], 'transaction' => ['status' => 'SUCCESS']];

        $this->notify($paid, 'wrong-secret')->assertStatus(401);
        $this->notify(['order' => ['invoice_number' => $number, 'amount' => 1000], 'transaction' => ['status' => 'SUCCESS']])->assertStatus(422);
        $this->notify(['order' => ['invoice_number' => $number, 'amount' => 99000], 'transaction' => ['status' => 'FAILED']])->assertOk();
        $this->notify(['order' => ['invoice_number' => 'PA000000UNKNOWN0', 'amount' => 99000], 'transaction' => ['status' => 'SUCCESS']])->assertStatus(404);

        $order = Order::firstOrFail();
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertFalse($order->student->hasAccessTo($course));
    }

    public function test_the_status_endpoint_settles_an_order_doku_reports_as_paid(): void
    {
        $course = $this->course();
        $this->fakeDoku();
        $number = $this->postJson('/api/checkout', $this->buyer())->json('order');

        Http::fake(['api-sandbox.doku.com/orders/v1/status/*' => Http::response(['transaction' => ['status' => 'SUCCESS']])]);

        $this->getJson("/api/checkout/orders/{$number}")->assertOk()->assertJson(['status' => 'paid', 'payment_url' => null]);
        $this->assertTrue(Order::firstOrFail()->student->hasAccessTo($course));
        $this->getJson('/api/checkout/orders/TIDAKADA')->assertStatus(404);
    }

    public function test_overdue_orders_are_closed_unless_doku_says_they_were_paid(): void
    {
        $course = $this->course();
        $student = Student::create(['name' => 'A', 'email' => 'a@example.test']);
        $make = fn (string $number) => Order::create([
            'number' => $number, 'student_id' => $student->id, 'course_id' => $course->id, 'amount' => 99000,
            'status' => OrderStatus::Pending, 'payment_url' => 'https://x', 'expires_at' => now()->subMinutes(5),
        ]);
        $late = $make('PA261114LATE0001');
        $unpaid = $make('PA261114UNPAID01');
        $fresh = Order::create([
            'number' => 'PA261114FRESH001', 'student_id' => $student->id, 'course_id' => $course->id, 'amount' => 99000,
            'status' => OrderStatus::Pending, 'payment_url' => 'https://x', 'expires_at' => now()->addMinutes(30),
        ]);

        Http::fake([
            '*/orders/v1/status/PA261114LATE0001' => Http::response(['transaction' => ['status' => 'SUCCESS']]),
            '*/orders/v1/status/*' => Http::response(['transaction' => ['status' => 'PENDING']]),
        ]);
        Cache::flush();

        $this->artisan('lms:settle-orders')->assertSuccessful();

        $this->assertSame(OrderStatus::Paid, $late->refresh()->status);
        $this->assertSame(OrderStatus::Expired, $unpaid->refresh()->status);
        $this->assertSame(OrderStatus::Pending, $fresh->refresh()->status);
    }
}
