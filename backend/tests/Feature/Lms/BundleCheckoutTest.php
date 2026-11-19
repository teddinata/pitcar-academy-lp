<?php

namespace Tests\Feature\Lms;

use App\Enums\CourseStatus;
use App\Enums\EnrollmentSource;
use App\Enums\OrderStatus;
use App\Jobs\SendPurchaseConversion;
use App\Models\Course;
use App\Models\CourseOffer;
use App\Models\Order;
use App\Models\ScalevPurchase;
use App\Models\Student;
use App\Services\Doku\Signature;
use App\Services\Lms\EnrollmentGrant;
use Database\Seeders\LmsCourseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Buying one online class can add the other at checkout for Rp79.000 more.
 * The offer rows come from LmsCourseSeeder, the same data production uses.
 */
class BundleCheckoutTest extends TestCase
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
            'kirimdev.api_key' => null,
            'services.meta.pixel_id' => '1364110365687038',
            'services.meta.access_token' => 'test-token',
            'services.meta.graph_version' => 'v21.0',
            'services.meta.test_event_code' => null,
        ]);

        Mail::fake();
        $this->seed(LmsCourseSeeder::class);
    }

    private function fakeDoku(): void
    {
        Http::fake([
            'api-sandbox.doku.com/checkout/v1/payment' => Http::response(['message' => ['SUCCESS'], 'response' => ['payment' => [
                'url' => 'https://sandbox.doku.com/checkout-link-v2/abc',
                'token_id' => 'abc',
                'expired_date' => now('Asia/Jakarta')->addHour()->format('YmdHis'),
            ]]]),
            'graph.facebook.com/*' => Http::response(['events_received' => 1]),
        ]);
    }

    private function buyer(array $over = []): array
    {
        return $over + ['course' => 'kelas-online-ac', 'name' => 'Budi Santoso', 'email' => 'budi@example.test', 'whatsapp' => '081234567890'];
    }

    private function notify(Order $order, int $amount)
    {
        $json = json_encode(['order' => ['invoice_number' => $order->number, 'amount' => $amount], 'transaction' => ['status' => 'SUCCESS']]);
        $id = 'req-'.$order->id;
        $time = '2026-11-17T02:00:00Z';

        return $this->call('POST', '/api/payments/doku/notification', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_CLIENT_ID' => self::CLIENT,
            'HTTP_REQUEST_ID' => $id,
            'HTTP_REQUEST_TIMESTAMP' => $time,
            'HTTP_SIGNATURE' => Signature::make(self::CLIENT, $id, $time, '/api/payments/doku/notification', $json, self::SECRET),
        ], $json);
    }

    public function test_seeder_sets_up_both_bundle_directions_once(): void
    {
        $this->seed(LmsCourseSeeder::class);

        $this->assertSame(2, Course::count());
        $this->assertSame(2, CourseOffer::count());
        $this->assertSame(['kelas-online-ac', 'kelas-inspeksi-mobil-bekas'], Course::orderBy('id')->pluck('tracking_id')->all());

        foreach (CourseOffer::with('course', 'offerCourse')->get() as $offer) {
            $this->assertSame(79000, $offer->price);
            $this->assertNotSame($offer->course_id, $offer->offer_course_id);
        }
    }

    public function test_seeder_keeps_a_price_an_admin_changed(): void
    {
        CourseOffer::query()->update(['price' => 69000]);

        $this->seed(LmsCourseSeeder::class);

        $this->assertSame([69000, 69000], CourseOffer::pluck('price')->all());
    }

    public function test_checkout_page_gets_price_and_offer_from_the_api(): void
    {
        $this->getJson('/api/checkout/courses/kelas-online-ac')
            ->assertOk()
            ->assertJson([
                'course' => ['slug' => 'kelas-online-ac', 'tracking_id' => 'kelas-online-ac', 'price' => 99000, 'strike_price' => 499000],
                'offers' => [['slug' => 'kelas-online-inspeksi', 'tracking_id' => 'kelas-inspeksi-mobil-bekas', 'price' => 99000, 'strike_price' => 499000, 'offer_price' => 79000]],
            ]);

        $this->getJson('/api/checkout/courses/kelas-online-inspeksi')
            ->assertJsonPath('offers.0.slug', 'kelas-online-ac')
            ->assertJsonPath('offers.0.offer_price', 79000);

        $this->getJson('/api/checkout/courses/tidak-ada')->assertNotFound();
    }

    public function test_switched_off_or_unpublished_offers_are_not_shown(): void
    {
        CourseOffer::query()->update(['is_active' => false]);
        $this->getJson('/api/checkout/courses/kelas-online-ac')->assertJsonCount(0, 'offers');

        CourseOffer::query()->update(['is_active' => true]);
        Course::where('slug', 'kelas-online-inspeksi')->update(['status' => CourseStatus::Draft]);
        $this->getJson('/api/checkout/courses/kelas-online-ac')->assertJsonCount(0, 'offers');
    }

    public function test_bundle_order_charges_main_price_plus_addon_price(): void
    {
        $this->fakeDoku();

        $this->postJson('/api/checkout', $this->buyer(['addons' => ['kelas-online-inspeksi']]))
            ->assertCreated()
            ->assertJson(['amount' => 178000, 'items' => [
                ['course' => 'kelas-online-ac', 'kind' => 'main', 'price' => 99000],
                ['course' => 'kelas-online-inspeksi', 'kind' => 'addon', 'price' => 79000, 'tracking_id' => 'kelas-inspeksi-mobil-bekas'],
            ]]);

        $order = Order::firstOrFail();
        $this->assertSame(178000, $order->amount);

        Http::assertSent(function (Request $request) {
            if (! str_ends_with($request->url(), '/checkout/v1/payment')) {
                return false;
            }
            $items = $request['order']['line_items'];

            return $request['order']['amount'] === 178000
                && count($items) === 2
                && array_sum(array_column($items, 'price')) === 178000
                && $items[1]['price'] === 79000;
        });
    }

    public function test_prices_sent_by_the_browser_are_ignored(): void
    {
        $this->fakeDoku();

        $this->postJson('/api/checkout', $this->buyer(['addons' => ['kelas-online-inspeksi'], 'amount' => 1000, 'price' => 1000, 'offer_price' => 1]))
            ->assertCreated()
            ->assertJsonPath('amount', 178000);
    }

    public function test_an_addon_the_course_does_not_offer_is_refused(): void
    {
        $this->fakeDoku();

        // A course cannot be its own add-on, and an unknown slug is not an offer.
        $this->postJson('/api/checkout', $this->buyer(['addons' => ['kelas-online-ac']]))->assertStatus(422)->assertJsonPath('code', 'addon_unavailable');
        $this->postJson('/api/checkout', $this->buyer(['addons' => ['kelas-rahasia']]))->assertStatus(422);

        CourseOffer::query()->update(['is_active' => false]);
        $this->postJson('/api/checkout', $this->buyer(['addons' => ['kelas-online-inspeksi']]))->assertStatus(422);

        $this->assertSame(0, Order::count());
    }

    public function test_an_addon_the_buyer_already_owns_is_refused(): void
    {
        $this->fakeDoku();
        $student = Student::create(['name' => 'Budi Santoso', 'email' => 'budi@example.test', 'whatsapp' => '6281234567890']);
        app(EnrollmentGrant::class)->grant($student, Course::where('slug', 'kelas-online-inspeksi')->first(), EnrollmentSource::Manual);

        $this->postJson('/api/checkout', $this->buyer(['addons' => ['kelas-online-inspeksi']]))
            ->assertStatus(409)
            ->assertJsonPath('code', 'addon_owned');

        // Without the add-on the main class can still be bought.
        $this->postJson('/api/checkout', $this->buyer())->assertCreated()->assertJsonPath('amount', 99000);
    }

    public function test_resubmitting_with_a_different_basket_makes_a_new_invoice(): void
    {
        $this->fakeDoku();

        $first = $this->postJson('/api/checkout', $this->buyer())->assertCreated();
        $second = $this->postJson('/api/checkout', $this->buyer([
            'addons' => ['kelas-online-inspeksi'], 'order' => $first['order'], 'claim' => $first['claim'],
        ]))->assertCreated();

        $this->assertNotSame($first['order'], $second['order']);

        // The same basket again keeps the payment page it already has.
        $third = $this->postJson('/api/checkout', $this->buyer([
            'addons' => ['kelas-online-inspeksi'], 'order' => $second['order'], 'claim' => $second['claim'],
        ]))->assertCreated();
        $this->assertSame($second['order'], $third['order']);
    }

    public function test_paying_a_bundle_opens_both_classes_and_reports_one_purchase(): void
    {
        $this->fakeDoku();
        Queue::fake([SendPurchaseConversion::class]);

        $this->postJson('/api/checkout', $this->buyer([
            'addons' => ['kelas-online-inspeksi'],
            'attribution' => ['utm_source' => 'facebook', 'utm_campaign' => 'ac-november', 'fbp' => 'fb.1.1.1', 'fbc' => 'fb.1.1.abc'],
        ]))->assertCreated();
        $order = Order::firstOrFail();
        $this->assertSame('facebook', $order->attribution['utm_source']);
        $this->assertSame('fb.1.1.1', $order->attribution['fbp']);

        // A notification for the price of one class alone is not this order.
        $this->notify($order, 99000)->assertStatus(422);
        $this->notify($order, 178000)->assertOk();
        $this->notify($order, 178000)->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $student = $order->student;
        $this->assertTrue($student->hasAccessTo(Course::where('slug', 'kelas-online-ac')->first()));
        $this->assertTrue($student->hasAccessTo(Course::where('slug', 'kelas-online-inspeksi')->first()));
        $this->assertSame(2, $student->enrollments()->count());
        Queue::assertPushed(SendPurchaseConversion::class, 1);
        $this->assertSame('Kelas Dasar Pemeriksaan & Perawatan AC Mobil + Kelas Inspeksi Mobil Bekas', $order->purchaseTitle());
    }

    public function test_a_new_bundle_buyer_gets_a_password_and_direct_entry(): void
    {
        $this->fakeDoku();
        $created = $this->postJson('/api/checkout', $this->buyer(['addons' => ['kelas-online-inspeksi']]))->assertCreated();
        $order = Order::firstOrFail();
        $this->notify($order, 178000)->assertOk();

        // The add-on this order opened must not count as a class the buyer already had.
        $this->postJson("/api/checkout/orders/{$order->number}/password", ['claim' => $created['claim']])
            ->assertOk()
            ->assertJsonMissing(['password' => null]);
        $this->postJson("/api/checkout/orders/{$order->number}/access", ['claim' => $created['claim']])
            ->assertOk()
            ->assertJsonStructure(['token']);
    }

    public function test_purchase_event_carries_order_value_and_the_pixels_product_ids(): void
    {
        $this->fakeDoku();
        $this->postJson('/api/checkout', $this->buyer([
            'addons' => ['kelas-online-inspeksi'],
            'attribution' => ['fbp' => 'fb.1.1.1', 'checkout_url' => 'https://academy.pitcar.co.id/checkout/?kelas=kelas-online-ac'],
        ]))->assertCreated();
        $order = Order::firstOrFail();

        $this->notify($order, 178000)->assertOk();

        Http::assertSent(function (Request $request) use ($order) {
            if (! str_contains($request->url(), 'graph.facebook.com')) {
                return false;
            }
            $event = $request['data'][0];

            return $event['event_name'] === 'Purchase'
                && $event['event_id'] === $order->number
                && $event['custom_data']['value'] === 178000
                && $event['custom_data']['currency'] === 'IDR'
                && $event['custom_data']['content_ids'] === ['kelas-online-ac', 'kelas-inspeksi-mobil-bekas']
                && $event['user_data']['em'] === hash('sha256', 'budi@example.test')
                && $event['user_data']['fbp'] === 'fb.1.1.1'
                && $event['event_source_url'] === 'https://academy.pitcar.co.id/checkout/?kelas=kelas-online-ac';
        });
        $this->assertNotNull($order->refresh()->meta_conversion_sent_at);

        // Run again (a queue retry): nothing is sent twice.
        Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);
        (new SendPurchaseConversion($order->id))->handle();
        Http::assertNothingSent();
    }

    public function test_sales_popup_counts_checkout_buyers_for_both_classes_of_a_bundle(): void
    {
        $this->fakeDoku();
        ScalevPurchase::create(['order_id' => 'S1', 'customer_name' => 'Sari Dewi', 'product_name' => 'Kelas Inspeksi Mobil Bekas', 'purchased_at' => now()->subDay()]);

        $this->postJson('/api/checkout', $this->buyer(['addons' => ['kelas-online-inspeksi']]))->assertCreated();
        $this->notify(Order::firstOrFail(), 178000)->assertOk();

        $inspeksi = $this->getJson('/api/purchases/recent?product=inspeksi')->assertOk();
        $this->assertSame(2, $inspeksi['total_purchases']);
        $this->assertSame('Budi S.', $inspeksi['purchases'][0]['name']);
        $this->assertSame('Sari D.', $inspeksi['purchases'][1]['name']);
        $this->assertStringNotContainsString('budi@example.test', $inspeksi->getContent());

        $this->getJson('/api/purchases/recent?product=ac')->assertJsonPath('total_purchases', 1);
    }
}
