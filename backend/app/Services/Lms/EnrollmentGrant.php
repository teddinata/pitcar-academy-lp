<?php

namespace App\Services\Lms;

use App\Enums\EnrollmentSource;
use App\Enums\OrderStatus;
use App\Jobs\SendPurchaseConversion;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnrollmentGrant
{
    /**
     * Opens a course for a student. Safe to call twice: a second call for the
     * same student and course reuses the row, and reopens it if it was revoked.
     */
    public function grant(Student $student, Course $course, EnrollmentSource $source, ?Order $order = null): Enrollment
    {
        $enrollment = Enrollment::firstOrNew(['student_id' => $student->id, 'course_id' => $course->id]);

        if (! $enrollment->exists || $enrollment->revoked_at !== null) {
            $enrollment->fill([
                'source' => $source,
                'order_id' => $order?->id,
                'granted_at' => now(),
                'revoked_at' => null,
            ])->save();
        }

        return $enrollment;
    }

    public function revoke(Enrollment $enrollment): void
    {
        $enrollment->update(['revoked_at' => now()]);
    }

    /**
     * The payment gateway may announce the same payment more than once. The
     * first announcement marks the order paid and opens the course; later ones
     * change nothing.
     */
    public function markPaid(Order $order, ?string $gatewayReference = null, ?string $via = null): Enrollment
    {
        $first = false;

        $enrollment = DB::transaction(function () use ($order, $gatewayReference, $via, &$first) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status !== OrderStatus::Paid) {
                $first = true;

                // A reference another order already holds must not stop this payment from being recorded.
                if ($gatewayReference !== null && Order::where('gateway_reference', $gatewayReference)->whereKeyNot($order->id)->exists()) {
                    Log::warning('Gateway reference reused', ['order' => $order->number, 'reference' => $gatewayReference]);
                    $gatewayReference = null;
                }
                $order->update([
                    'status' => OrderStatus::Paid,
                    'paid_at' => now(),
                    'gateway_reference' => $gatewayReference ?? $order->gateway_reference,
                    'paid_via' => $via,
                ]);
            }

            // A bundle opens every course in it; the main course's enrollment is returned.
            $enrollments = $order->purchasedCourses()->map(
                fn (Course $course) => $this->grant($order->student, $course, EnrollmentSource::from($order->gateway), $order)
            );

            return $enrollments->first();
        });

        if ($first) {
            $order->refresh();
            app(LoginLink::class)->send($order->student, $order->course, $order->purchaseTitle());
            app(AccessWhatsApp::class)->afterPayment($order);
            SendPurchaseConversion::dispatch($order->id)->afterCommit();
        }

        return $enrollment;
    }
}
