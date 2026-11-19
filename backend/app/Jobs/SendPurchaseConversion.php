<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Reports a paid class order to Meta's Conversions API as a Purchase.
 *
 * Scalev used to report purchases for the ads. Now that payment happens on our
 * own checkout, this is what keeps campaigns optimising on Purchase. The
 * checkout page sends the same event from the browser with the order number
 * as its eventID, so Meta counts one purchase, and a buyer who closes the tab
 * before the pixel fires is still counted from here.
 */
class SendPurchaseConversion implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 300];

    public function __construct(public readonly int $orderId) {}

    public function handle(): void
    {
        $pixelId = config('services.meta.pixel_id');
        $token = config('services.meta.access_token');

        // Not configured is a valid state: the browser pixel still reports the purchase.
        if (blank($pixelId) || blank($token)) {
            return;
        }

        $order = Order::with(['student', 'course', 'items.course'])->find($this->orderId);

        if ($order === null || $order->paid_at === null || $order->meta_conversion_sent_at !== null) {
            return;
        }

        $response = Http::timeout((int) config('services.meta.timeout'))
            ->asJson()
            ->post(
                sprintf('https://graph.facebook.com/%s/%s/events', config('services.meta.graph_version'), $pixelId),
                array_filter([
                    'access_token' => $token,
                    'test_event_code' => config('services.meta.test_event_code'),
                    'data' => [self::event($order)],
                ]),
            );

        if ($response->failed()) {
            Log::warning('order.meta_conversion_failed', [
                'order' => $order->number,
                'status' => $response->status(),
                'error' => $response->json('error.message'),
            ]);
        }

        $response->throw();

        $order->forceFill(['meta_conversion_sent_at' => now()])->saveQuietly();
    }

    /** @return array<string, mixed> */
    public static function event(Order $order): array
    {
        $attribution = (array) $order->attribution;
        $items = $order->items->isEmpty()
            ? collect([new OrderItem(['course_id' => $order->course_id, 'kind' => OrderItem::MAIN, 'price' => $order->amount])])->each(fn ($i) => $i->setRelation('course', $order->course))
            : $order->items;

        return array_filter([
            'event_name' => 'Purchase',
            'event_time' => $order->paid_at->getTimestamp(),
            // The browser pixel sends the same id, so Meta keeps one of the two.
            'event_id' => $order->number,
            'event_source_url' => $attribution['checkout_url'] ?? config('lms.frontend_url').'/checkout/',
            'action_source' => 'website',
            'user_data' => self::userData($order, $attribution),
            'custom_data' => [
                'currency' => 'IDR',
                'value' => $order->amount,
                'content_type' => 'product',
                'content_ids' => $items->map(fn (OrderItem $item) => $item->course->trackingId())->values()->all(),
                'contents' => $items->map(fn (OrderItem $item) => [
                    'id' => $item->course->trackingId(),
                    'quantity' => 1,
                    'item_price' => $item->price,
                ])->values()->all(),
                'num_items' => $items->count(),
                'order_id' => $order->number,
            ],
        ], fn ($value) => $value !== null && $value !== []);
    }

    /** @return array<string, mixed> */
    private static function userData(Order $order, array $attribution): array
    {
        $name = trim((string) $order->student->name);

        return array_filter([
            'em' => self::hash($order->student->email),
            'ph' => self::hash(preg_replace('/\D/', '', (string) $order->student->whatsapp)),
            'fn' => self::hash(Str::before($name, ' ')),
            'ln' => self::hash(Str::contains($name, ' ') ? Str::afterLast($name, ' ') : null),
            'country' => self::hash('id'),
            'external_id' => self::hash('student-'.$order->student_id),
            // Not hashed, by Meta's spec.
            'fbp' => $attribution['fbp'] ?? null,
            'fbc' => $attribution['fbc'] ?? null,
            'client_ip_address' => $attribution['ip'] ?? null,
            'client_user_agent' => $attribution['user_agent'] ?? null,
        ], fn ($value) => filled($value));
    }

    /** Meta requires lowercase, trimmed, then SHA-256; blanks stay blank. */
    private static function hash(?string $value): ?string
    {
        $value = Str::lower(trim((string) $value));

        return $value === '' ? null : hash('sha256', $value);
    }
}
