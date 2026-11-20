<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\PurchaseFeedController;
use App\Models\ScalevPurchase;
use App\Models\ScalevUnpaidOrder;
use App\Services\Lms\ScalevImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SyncScalevPurchases extends Command
{
    protected $signature = 'scalev:sync-purchases';

    protected $description = 'Pull recent Scalev orders: paid ones feed the sales popup and open the class in the LMS, unpaid ones go on the follow-up list';

    // Guard rail so a runaway cursor can never loop forever. 10 pages x 25
    // orders = 250 of the newest paid orders, far more than any popup needs.
    private const MAX_PAGES = 10;

    public function handle(ScalevImport $import): int
    {
        $apiKey = (string) config('services.scalev.api_key');

        if ($apiKey === '') {
            $this->info('SCALEV_API_KEY is not set — skipping the sync.');

            return self::SUCCESS;
        }

        $baseUrl = rtrim((string) config('services.scalev.api_url'), '/');
        $timeout = (int) config('services.scalev.timeout', 15);

        $cursor = null;
        $created = 0;
        $updated = 0;
        $skipped = 0;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $response = Http::baseUrl($baseUrl)
                ->timeout($timeout)
                ->withToken($apiKey)
                ->get('/v3/orders', array_filter([
                    'page_size' => 25,
                    'columns' => ScalevImport::COLUMNS,
                    'next_cursor' => $cursor,
                ]));

            if ($response->failed()) {
                $this->error("Scalev API returned {$response->status()}: {$response->body()}");

                return self::FAILURE;
            }

            $orders = $response->json('data', []);

            // While Scalev still sells, its buyers keep getting the class here.
            foreach ($orders as $order) {
                $import->apply($order);
            }

            ['created' => $c, 'updated' => $u, 'skipped' => $s] = $this->ingest($orders);
            $created += $c;
            $updated += $u;
            $skipped += $s;

            if (! $response->json('has_next') || ! $response->json('next_cursor')) {
                break;
            }

            $cursor = $response->json('next_cursor');
        }

        ScalevUnpaidOrder::where('ordered_at', '<', now()->subDays(30))->delete();

        $this->info("Scalev sync done: {$created} new, {$updated} updated, {$skipped} skipped.");

        // Fresh purchases should hit the popup without waiting out the feed
        // cache's TTL, on every page's feed.
        foreach (PurchaseFeedController::cacheKeys() as $key) {
            Cache::forget($key);
        }

        return self::SUCCESS;
    }

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    private function ingest(array $orders): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($orders as $order) {
            // Only completed orders count as social proof: money moved and
            // the funnel finished. Pending/draft/refunded never counts.
            if (($order['status'] ?? null) !== 'completed') {
                $skipped++;

                continue;
            }

            if (($order['is_probably_spam'] ?? false) === true) {
                $skipped++;

                continue;
            }

            $orderId = $order['order_id'] ?? $order['id'] ?? null;
            $purchasedAt = $order['confirmed_time'] ?? $order['created_at'] ?? null;

            if (! $orderId || ! $purchasedAt) {
                $skipped++;

                continue;
            }

            $attributes = [
                'customer_name' => $order['customer']['name'] ?? 'Pembeli',
                'customer_city' => $order['destination_address']['city'] ?? null,
                'product_name' => $this->productName($order),
                'amount' => isset($order['gross_revenue']) ? (int) round((float) $order['gross_revenue']) : null,
                'purchased_at' => $purchasedAt,
            ];

            $existing = ScalevPurchase::find($orderId);

            if ($existing === null) {
                ScalevPurchase::create(['order_id' => $orderId, ...$attributes]);
                $created++;
            } else {
                $existing->fill($attributes)->save();
                $updated++;
            }
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * final_variants is a map of variant name to quantity. One checkout can
     * hold several classes, so every name is kept: the feed matches each
     * page's patterns against the whole string, and the popup lists them all.
     */
    private function productName(array $order): ?string
    {
        $variants = $order['final_variants'] ?? [];

        if (is_array($variants) && $variants !== []) {
            return implode(' + ', array_map('strval', array_keys($variants)));
        }

        return null;
    }
}
