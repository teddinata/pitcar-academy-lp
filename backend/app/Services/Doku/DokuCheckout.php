<?php

namespace App\Services\Doku;

use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DokuCheckout
{
    private const PAYMENT_PATH = '/checkout/v1/payment';

    private const STATUS_PATH = '/orders/v1/status/';

    public function configured(): bool
    {
        return filled(config('doku.client_id')) && filled(config('doku.secret_key'));
    }

    /**
     * Opens a payment page for the order.
     *
     * @return array{url: string, token: ?string, expires_at: CarbonImmutable}
     */
    public function createPayment(Order $order): array
    {
        $student = $order->student;
        $minutes = (int) config('doku.payment_due_minutes');

        $payload = [
            'order' => [
                'amount' => $order->amount,
                'invoice_number' => $order->number,
                'currency' => 'IDR',
                'language' => 'ID',
                'auto_redirect' => false,
                // One line per course; DOKU checks that they add up to the amount.
                'line_items' => $this->lineItems($order),
            ],
            'payment' => ['payment_due_date' => $minutes],
            'customer' => array_filter([
                'id' => 'STU-'.$student->id,
                'name' => self::plain($student->name),
                'email' => $student->email,
                'phone' => $student->whatsapp,
                'country' => 'ID',
            ]),
            'additional_info' => [
                'override_notification_url' => rtrim((string) config('app.url'), '/').config('doku.notification_path'),
            ],
        ];

        if (filled(config('doku.return_url'))) {
            $payload['order']['callback_url'] = config('doku.return_url').'?order='.$order->number;
        }

        if (config('doku.payment_methods') !== []) {
            $payload['payment']['payment_method_types'] = config('doku.payment_methods');
        }

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $response = Http::withHeaders($this->headers(self::PAYMENT_PATH, $body))
            ->withBody($body, 'application/json')
            ->timeout(20)
            ->post(config('doku.base_url').self::PAYMENT_PATH);

        $url = $response->json('response.payment.url');

        if (! $response->successful() || blank($url)) {
            throw new DokuException('DOKU menolak permintaan pembayaran: '.Str::limit($response->body(), 300));
        }

        // DOKU reports the deadline as yyyyMMddHHmmss in WIB.
        $expires = $response->json('response.payment.expired_date');

        return [
            'url' => $url,
            'token' => $response->json('response.payment.token_id'),
            'expires_at' => $expires
                ? CarbonImmutable::createFromFormat('YmdHis', (string) $expires, 'Asia/Jakarta')->utc()
                : CarbonImmutable::now()->addMinutes($minutes),
        ];
    }

    /** PENDING, SUCCESS, FAILED, EXPIRED, REFUNDED, TIMEOUT or REDIRECT; null when DOKU has no record yet. */
    public function status(Order $order): ?string
    {
        $path = self::STATUS_PATH.$order->number;

        $response = Http::withHeaders($this->headers($path, null))
            ->timeout(15)
            ->get(config('doku.base_url').$path);

        return $response->successful() ? $response->json('transaction.status') : null;
    }

    /** @return list<array{id: string, name: string, quantity: int, price: int}> */
    private function lineItems(Order $order): array
    {
        $items = $order->items()->with('course')->get();

        // An order from before bundles is its main course at the full amount.
        if ($items->isEmpty()) {
            return [['id' => (string) $order->course_id, 'name' => Str::limit(self::plain($order->course->title), 60, ''), 'quantity' => 1, 'price' => $order->amount]];
        }

        return $items->map(fn ($item) => [
            'id' => (string) $item->course_id,
            'name' => Str::limit(self::plain($item->course->title), 60, ''),
            'quantity' => 1,
            'price' => $item->price,
        ])->all();
    }

    /**
     * Asks DOKU about an invoice that does not exist. A 404 "data_not_found"
     * means DOKU accepted our keys; "invalid_signature" means they are wrong.
     *
     * @return array{status: int, code: ?string}
     */
    public function probe(): array
    {
        $path = self::STATUS_PATH.'PACHECK'.Str::upper(Str::random(8));

        $response = Http::withHeaders($this->headers($path, null))
            ->timeout(15)
            ->get(config('doku.base_url').$path);

        return ['status' => $response->status(), 'code' => $response->json('error.code')];
    }

    /** DOKU rejects a request whose text holds anything outside a small character set. */
    private static function plain(string $text): string
    {
        $text = Str::ascii(str_replace('&', 'dan', $text));

        return trim(preg_replace('/\s+/', ' ', preg_replace("/[^a-zA-Z0-9 .\-\/+,=_:'@%()]/", ' ', $text)));
    }

    private function headers(string $target, ?string $body): array
    {
        $clientId = (string) config('doku.client_id');
        $requestId = (string) Str::uuid();
        $timestamp = now()->utc()->format('Y-m-d\TH:i:s\Z');

        return [
            'Client-Id' => $clientId,
            'Request-Id' => $requestId,
            'Request-Timestamp' => $timestamp,
            'Signature' => Signature::make($clientId, $requestId, $timestamp, $target, $body, (string) config('doku.secret_key')),
        ];
    }
}
