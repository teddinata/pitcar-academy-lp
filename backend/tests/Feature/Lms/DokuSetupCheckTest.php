<?php

namespace Tests\Feature\Lms;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Doku\Signature;
use Database\Seeders\LmsCourseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * lms:doku-check, and the record of how each order learned it was paid:
 * the two tools for confirming DOKU notifications reach production.
 */
class DokuSetupCheckTest extends TestCase
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
            'doku.base_url' => 'https://api.doku.com',
            'doku.return_url' => 'https://academy.pitcar.co.id/checkout/',
            'app.url' => 'https://api-academy.test',
            'lms.frontend_url' => 'https://academy.pitcar.co.id',
            'kirimdev.api_key' => null,
        ]);
        Mail::fake();
        $this->seed(LmsCourseSeeder::class);
    }

    public function test_passes_when_doku_accepts_the_keys_and_the_notification_route_answers(): void
    {
        Http::fake([
            'api.doku.com/orders/v1/status/*' => Http::response(['error' => ['code' => 'data_not_found']], 404),
            'api-academy.test/api/payments/doku/notification' => Http::response(['message' => 'Unknown order.'], 404),
        ]);

        $this->artisan('lms:doku-check')
            ->expectsOutputToContain('Siap menerima pembayaran.')
            ->assertSuccessful();

        // The test notification is signed exactly as DOKU signs, against our own path.
        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), '/api/payments/doku/notification')) {
                return false;
            }

            return Signature::matches(
                $request->header('Signature')[0],
                self::CLIENT,
                $request->header('Request-Id')[0],
                $request->header('Request-Timestamp')[0],
                '/api/payments/doku/notification',
                $request->body(),
                self::SECRET,
            );
        });
    }

    public function test_fails_when_doku_rejects_the_keys(): void
    {
        Http::fake([
            'api.doku.com/orders/v1/status/*' => Http::response(['error' => ['code' => 'invalid_signature']], 400),
            'api-academy.test/*' => Http::response([], 404),
        ]);

        $this->artisan('lms:doku-check')->assertFailed();
    }

    public function test_fails_when_the_notification_route_rejects_our_signature(): void
    {
        Http::fake([
            'api.doku.com/orders/v1/status/*' => Http::response(['error' => ['code' => 'data_not_found']], 404),
            'api-academy.test/*' => Http::response(['message' => 'Invalid signature.'], 401),
        ]);

        $this->artisan('lms:doku-check')->assertFailed();
    }

    public function test_fails_offline_when_app_url_is_not_public_https(): void
    {
        config(['app.url' => 'http://localhost:8000']);

        $this->artisan('lms:doku-check --offline')->assertFailed();
    }

    public function test_the_real_endpoint_answers_unknown_order_to_a_signed_test_notification(): void
    {
        Log::spy();
        $body = json_encode(['order' => ['invoice_number' => 'DOKUCHECKX', 'amount' => 0], 'transaction' => ['status' => 'SUCCESS']]);

        $this->call('POST', '/api/payments/doku/notification', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_CLIENT_ID' => self::CLIENT,
            'HTTP_REQUEST_ID' => 'r1',
            'HTTP_REQUEST_TIMESTAMP' => 't1',
            'HTTP_SIGNATURE' => Signature::make(self::CLIENT, 'r1', 't1', '/api/payments/doku/notification', $body, self::SECRET),
        ], $body)->assertNotFound();

        $this->assertSame(0, Order::count());
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => $message === 'DOKU notification for unknown order');
    }

    public function test_a_rejected_notification_is_logged_without_secrets(): void
    {
        Log::spy();

        $this->postJson('/api/payments/doku/notification', ['order' => ['invoice_number' => 'PA1']], ['Client-Id' => self::CLIENT, 'Signature' => 'HMACSHA256=palsu'])
            ->assertUnauthorized();

        Log::shouldHaveReceived('warning')->withArgs(function ($message, $context) {
            return $message === 'DOKU notification rejected'
                && $context['invoice'] === 'PA1'
                && $context['client_id_matches'] === true
                && ! str_contains(json_encode($context), self::SECRET)
                && ! str_contains(json_encode($context), 'palsu');
        });
    }

    public function test_orders_record_whether_the_notification_or_the_status_check_paid_them(): void
    {
        Http::fake([
            'api.doku.com/checkout/v1/payment' => Http::response(['response' => ['payment' => [
                'url' => 'https://checkout.doku.com/x', 'token_id' => 'x', 'expired_date' => now('Asia/Jakarta')->addHour()->format('YmdHis'),
            ]]]),
            'api.doku.com/orders/v1/status/*' => Http::response(['transaction' => ['status' => 'SUCCESS']]),
        ]);
        $buyer = ['course' => 'kelas-online-ac', 'name' => 'Budi', 'whatsapp' => '081234567890'];

        $this->postJson('/api/checkout', $buyer + ['email' => 'a@example.test'])->assertCreated();
        $byNotification = Order::latest('id')->first();
        $body = json_encode(['order' => ['invoice_number' => $byNotification->number, 'amount' => 99000], 'transaction' => ['status' => 'SUCCESS']]);
        $this->call('POST', '/api/payments/doku/notification', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_CLIENT_ID' => self::CLIENT,
            'HTTP_REQUEST_ID' => 'r2',
            'HTTP_REQUEST_TIMESTAMP' => 't2',
            'HTTP_SIGNATURE' => Signature::make(self::CLIENT, 'r2', 't2', '/api/payments/doku/notification', $body, self::SECRET),
        ], $body)->assertOk();

        $this->postJson('/api/checkout', $buyer + ['email' => 'b@example.test'])->assertCreated();
        $byStatusCheck = Order::latest('id')->first();
        $this->getJson('/api/checkout/orders/'.$byStatusCheck->number)->assertJsonPath('status', 'paid');

        $this->assertSame(Order::PAID_VIA_NOTIFICATION, $byNotification->refresh()->paid_via);
        $this->assertSame(Order::PAID_VIA_STATUS_CHECK, $byStatusCheck->refresh()->paid_via);
        $this->assertSame(OrderStatus::Paid, $byStatusCheck->status);
    }
}
