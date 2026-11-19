<?php

namespace Tests\Feature;

use App\Models\ScalevPurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScalevPurchaseFeedTest extends TestCase
{
    use RefreshDatabase;

    private function orderPayload(string $orderId, string $name, ?string $city, string $createdAt): array
    {
        return [
            'order_id' => $orderId,
            'created_at' => $createdAt,
            'confirmed_time' => $createdAt,
            'payment_status' => 'paid',
            'status' => 'completed',
            'gross_revenue' => '99000.00',
            'final_variants' => ['Kelas Inspeksi Mobil Bekas' => 1],
            'customer' => ['name' => $name],
            'destination_address' => ['city' => $city],
            'is_probably_spam' => false,
        ];
    }

    private function fakeScalevApi(array $orders): void
    {
        config(['services.scalev.api_key' => 'test-key']);

        Http::fake([
            'api.scalev.com/v3/orders*' => Http::response([
                'data' => $orders,
                'is_paginated' => true,
                'has_next' => false,
                'next_cursor' => null,
                'page_size' => 25,
            ]),
        ]);
    }

    public function test_it_pulls_paid_orders_into_the_local_table(): void
    {
        $this->fakeScalevApi([
            $this->orderPayload('01A', 'Budi Santoso', 'Surabaya', '2026-09-30T02:00:00Z'),
            $this->orderPayload('01B', 'Siti', null, '2026-09-30T03:00:00Z'),
        ]);

        $this->artisan('scalev:sync-purchases')->assertSuccessful();

        $this->assertDatabaseHas('scalev_purchases', [
            'order_id' => '01A',
            'customer_name' => 'Budi Santoso',
            'customer_city' => 'Surabaya',
            'product_name' => 'Kelas Inspeksi Mobil Bekas',
            'amount' => 99000,
        ]);
        $this->assertDatabaseHas('scalev_purchases', [
            'order_id' => '01B',
            'customer_name' => 'Siti',
            'customer_city' => null,
        ]);
    }

    public function test_resyncing_the_same_order_updates_instead_of_duplicating(): void
    {
        config(['services.scalev.api_key' => 'test-key']);

        $order = fn (string $city) => [
            'data' => [$this->orderPayload('01A', 'Budi Santoso', $city, '2026-09-30T02:00:00Z')],
            'is_paginated' => true, 'has_next' => false, 'next_cursor' => null, 'page_size' => 25,
        ];

        // Two fakes on the same URL merge, and the first stub always wins —
        // so both syncs must share one sequence instead.
        Http::fake([
            'api.scalev.com/v3/orders*' => Http::sequence()
                ->push($order('Surabaya'))
                ->push($order('Jakarta')),
        ]);

        $this->artisan('scalev:sync-purchases')->assertSuccessful();
        $this->artisan('scalev:sync-purchases')->assertSuccessful();

        $this->assertSame(1, ScalevPurchase::count());
        $this->assertSame('Jakarta', ScalevPurchase::sole()->customer_city);
    }

    public function test_spam_orders_never_become_social_proof(): void
    {
        $spam = $this->orderPayload('01C', 'Spam Bot', 'Spam City', '2026-09-30T04:00:00Z');
        $spam['is_probably_spam'] = true;

        $this->fakeScalevApi([$spam]);

        $this->artisan('scalev:sync-purchases')->assertSuccessful();

        $this->assertSame(0, ScalevPurchase::count());
    }

    public function test_completed_orders_count_but_pending_do_not(): void
    {
        config(['services.scalev.api_key' => 'test-key']);

        $completed = $this->orderPayload('01D', 'Dewi Lestari', 'Bandung', '2026-09-30T05:00:00Z');
        $pending = $this->orderPayload('01E', 'Calon Pembeli', 'Medan', '2026-09-30T06:00:00Z');
        $pending['status'] = 'pending';
        $pending['payment_status'] = 'unpaid';

        $this->fakeScalevApi([$completed, $pending]);

        $this->artisan('scalev:sync-purchases')->assertSuccessful();

        $this->assertSame(1, ScalevPurchase::count());
        $this->assertSame('Dewi Lestari', ScalevPurchase::sole()->customer_name);
    }

    public function test_it_skips_the_sync_without_an_api_key(): void
    {
        config(['services.scalev.api_key' => null]);
        Http::fake();

        $this->artisan('scalev:sync-purchases')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_recent_feed_masks_names_and_reports_the_total(): void
    {
        ScalevPurchase::create([
            'order_id' => '01A',
            'customer_name' => 'Budi Santoso',
            'customer_city' => 'Surabaya',
            'product_name' => 'Kelas Inspeksi Mobil Bekas',
            'amount' => 99000,
            'purchased_at' => now()->subMinutes(3),
        ]);
        ScalevPurchase::create([
            'order_id' => '01B',
            'customer_name' => 'Siti',
            'customer_city' => null,
            'product_name' => null,
            'amount' => null,
            'purchased_at' => now()->subMinutes(1),
        ]);

        $response = $this->getJson('/api/purchases/recent');

        $response->assertSuccessful()
            ->assertJsonPath('total_purchases', 2)
            ->assertJsonPath('purchases.0.name', 'Siti')
            ->assertJsonPath('purchases.1.name', 'Budi S.');

        $payload = $response->getContent();

        // Only masked name + time. The full name, order id, city and product
        // must never reach the browser.
        $this->assertStringNotContainsString('Santoso', $payload);
        $this->assertStringNotContainsString('01A', $payload);
        $this->assertStringNotContainsString('Surabaya', $payload);
        $this->assertStringNotContainsString('Kelas Inspeksi', $payload);
    }

    public function test_the_feed_can_be_limited_to_one_class(): void
    {
        $make = fn (string $id, string $name, ?string $product, int $minutesAgo) => ScalevPurchase::create([
            'order_id' => $id,
            'customer_name' => $name,
            'product_name' => $product,
            'purchased_at' => now()->subMinutes($minutesAgo),
        ]);

        $make('01A', 'Budi Santoso', 'Kelas Inspeksi Mobil Bekas', 5);
        $make('01B', 'Rina Wati', 'Kelas Dasar Pemeriksaan & Perawatan AC Mobil', 3);
        $make('01C', 'Siti', null, 1);

        $this->getJson('/api/purchases/recent?product=ac')
            ->assertSuccessful()
            ->assertJsonPath('total_purchases', 1)
            ->assertJsonCount(1, 'purchases')
            ->assertJsonPath('purchases.0.name', 'Rina W.');

        $this->getJson('/api/purchases/recent?product=inspeksi')
            ->assertSuccessful()
            ->assertJsonPath('total_purchases', 1)
            ->assertJsonPath('purchases.0.name', 'Budi S.');

        // Unknown product: empty, never everybody.
        $this->getJson('/api/purchases/recent?product=nope')
            ->assertSuccessful()
            ->assertJsonPath('total_purchases', 0)
            ->assertJsonCount(0, 'purchases');

        // No filter keeps the old mixed feed.
        $this->getJson('/api/purchases/recent')->assertJsonPath('total_purchases', 3);
    }
}
