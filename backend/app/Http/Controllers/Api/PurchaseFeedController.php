<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\ScalevPurchase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PurchaseFeedController extends Controller
{
    /** Prefix shared with SyncScalevPurchases, which clears every variant. */
    public const CACHE_KEY = 'purchases:recent-feed';

    public function index(Request $request): JsonResponse
    {
        // ?product=ac limits the feed to one class. Without it the feed stays
        // mixed, as before, so an older front end keeps working.
        $product = $request->query('product');
        $product = is_string($product) && $product !== '' ? strtolower($product) : null;

        $patterns = null;

        if ($product !== null) {
            $patterns = config("services.scalev.products.{$product}");

            // Unknown product: say nothing rather than show someone else's buyers.
            if (! is_array($patterns) || $patterns === []) {
                return response()->json(['total_purchases' => 0, 'purchases' => []]);
            }
        }

        $feed = Cache::remember(self::cacheKey($product), now()->addMinute(), function () use ($patterns) {
            $matches = fn (Builder $q, string $column) => $q->where(function (Builder $q) use ($patterns, $column) {
                foreach ($patterns as $pattern) {
                    $q->orWhereRaw("LOWER({$column}) LIKE ?", ['%'.mb_strtolower($pattern).'%']);
                }
            });

            $scalev = fn (): Builder => ScalevPurchase::query()
                ->when($patterns !== null, fn (Builder $q) => $matches($q, 'product_name'));

            // Since the move off Scalev, buyers come through our own checkout too; a bundle counts for both its classes.
            $orders = fn (): Builder => Order::query()
                ->where('status', OrderStatus::Paid)
                ->whereNotNull('paid_at')
                ->when($patterns !== null, fn (Builder $q) => $q->whereHas('items.course', fn (Builder $c) => $matches($c, 'title')));

            $recent = $scalev()->orderByDesc('purchased_at')->limit(10)->get()
                ->map(fn (ScalevPurchase $purchase) => $purchase->toPopupData())
                ->concat($orders()->with('student:id,name')->orderByDesc('paid_at')->limit(10)->get()
                    ->map(fn (Order $order) => [
                        'name' => ScalevPurchase::mask($order->student?->name),
                        'purchased_at' => $order->paid_at->toIso8601String(),
                    ]))
                ->sortByDesc(fn (array $p) => strtotime($p['purchased_at']))
                ->take(10)
                ->values()
                ->all();

            return [
                'total_purchases' => $scalev()->count() + $orders()->count(),
                'purchases' => $recent,
            ];
        });

        return response()->json($feed);
    }

    public static function cacheKey(?string $product): string
    {
        return $product === null ? self::CACHE_KEY : self::CACHE_KEY.':'.$product;
    }

    /** Every cache key the feed can be stored under. */
    public static function cacheKeys(): array
    {
        return [
            self::cacheKey(null),
            ...array_map(self::cacheKey(...), array_keys((array) config('services.scalev.products', []))),
        ];
    }
}
