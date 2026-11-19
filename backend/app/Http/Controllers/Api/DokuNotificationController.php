<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Doku\Signature;
use App\Services\Lms\EnrollmentGrant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DokuNotificationController extends Controller
{
    public function __invoke(Request $request, EnrollmentGrant $grant): JsonResponse
    {
        $secret = (string) config('doku.secret_key');
        $clientId = (string) $request->header('Client-Id');

        $genuine = $secret !== ''
            && $clientId === (string) config('doku.client_id')
            && Signature::matches(
                (string) $request->header('Signature'),
                $clientId,
                (string) $request->header('Request-Id'),
                (string) $request->header('Request-Timestamp'),
                config('doku.notification_path'),
                $request->getContent(),
                $secret,
            );

        $invoice = (string) $request->input('order.invoice_number');

        if (! $genuine) {
            // Never the secret or the signature itself: enough to tell a wrong key from a wrong URL.
            Log::warning('DOKU notification rejected', [
                'invoice' => $invoice,
                'client_id_matches' => $clientId === (string) config('doku.client_id'),
                'has_signature' => $request->hasHeader('Signature'),
                'secret_configured' => $secret !== '',
            ]);

            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $order = Order::where('number', $invoice)->first();

        // Any 2xx stops DOKU retrying, so an order we cannot place answers 404 and stays visible in their log.
        if (! $order) {
            Log::warning('DOKU notification for unknown order', ['invoice' => $invoice]);

            return response()->json(['message' => 'Unknown order.'], 404);
        }

        $status = (string) $request->input('transaction.status');

        if ($status !== 'SUCCESS') {
            // FAILED is not final: the buyer can retry on the same payment page.
            Log::info('DOKU notification ignored', ['order' => $order->number, 'status' => $status]);

            return response()->json(['message' => 'OK']);
        }

        if ((int) $request->input('order.amount') !== $order->amount) {
            Log::warning('DOKU amount mismatch', ['order' => $order->number, 'paid' => $request->input('order.amount')]);

            return response()->json(['message' => 'Amount mismatch.'], 422);
        }

        $grant->markPaid($order, $request->input('transaction.original_request_id'), Order::PAID_VIA_NOTIFICATION);
        Log::info('DOKU notification paid', ['order' => $order->number]);

        return response()->json(['message' => 'OK']);
    }
}
