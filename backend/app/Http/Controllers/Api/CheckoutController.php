<?php

namespace App\Http\Controllers\Api;

use App\Enums\CourseStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCheckoutRequest;
use App\Models\Course;
use App\Models\CourseOffer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Doku\DokuCheckout;
use App\Services\Doku\DokuException;
use App\Services\Lms\AddonUnavailable;
use App\Services\Lms\AlreadyEnrolled;
use App\Services\Lms\Checkout;
use App\Services\Lms\LoginLink;
use App\Services\Lms\OrderReconciler;
use App\Services\Lms\PasswordIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CheckoutController extends Controller
{
    public function store(StoreCheckoutRequest $request, Checkout $checkout, DokuCheckout $doku): JsonResponse
    {
        $course = Course::where('slug', $request->validated('course'))
            ->where('status', CourseStatus::Published)
            ->first();

        if (! $course) {
            return response()->json(['message' => 'Kelas tidak ditemukan.'], 404);
        }

        if (! $doku->configured()) {
            return response()->json(['message' => 'Pembayaran belum aktif. Hubungi admin Pitcar Academy.'], 503);
        }

        // Only the known keys survive validation; the request's own address and browser help Meta match the buyer.
        $attribution = array_filter([
            ...(array) $request->validated('attribution', []),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ], fn ($value) => filled($value));

        try {
            ['order' => $order, 'claim' => $claim] = $checkout->start(
                $course,
                $request->validated(),
                (array) $request->validated('addons', []),
                $attribution,
            );
        } catch (AddonUnavailable $e) {
            return response()->json([
                'code' => $e->owned ? 'addon_owned' : 'addon_unavailable',
                'message' => $e->getMessage(),
            ], $e->owned ? 409 : 422);
        } catch (AlreadyEnrolled) {
            return response()->json([
                'code' => 'already_enrolled',
                'message' => 'Email ini sudah punya akses ke kelas ini. Masuk untuk mulai belajar.',
            ], 409);
        } catch (DokuException $e) {
            report($e);

            return response()->json(['message' => 'Halaman pembayaran tidak bisa dibuka. Coba lagi sebentar lagi.'], 502);
        }

        return response()->json($this->payload($order) + ['claim' => $claim], 201);
    }

    /**
     * What the checkout page shows for a course: its price and the courses it
     * recommends as add-ons. Public: it holds nothing the sales page does not.
     */
    public function course(string $slug): JsonResponse
    {
        $course = Course::where('slug', $slug)->where('status', CourseStatus::Published)->first();

        if (! $course) {
            return response()->json(['message' => 'Kelas tidak ditemukan.'], 404);
        }

        $offers = $course->offers()->available()->with('offerCourse')->get();

        // Shown on the sales page as "bergabung dengan N siswa lain". Asked on
        // every visit, so it is counted at most once a minute.
        $students = Cache::remember('course-students:'.$course->id, 60, fn () => $course->enrollments()->whereNull('revoked_at')->count());

        return response()->json([
            'course' => self::coursePayload($course) + ['students' => $students],
            'offers' => $offers->map(fn (CourseOffer $offer) => self::coursePayload($offer->offerCourse) + [
                'offer_price' => $offer->price,
                'headline' => $offer->headline,
            ])->values(),
        ]);
    }

    /** @return array{slug: string, title: string, tracking_id: string, price: int, strike_price: ?int} */
    private static function coursePayload(Course $course): array
    {
        return [
            'slug' => $course->slug,
            'title' => $course->title,
            'tracking_id' => $course->trackingId(),
            'price' => $course->price,
            'strike_price' => $course->strike_price,
        ];
    }

    /** Polled by the checkout page while the buyer pays. The order number is the only key. */
    public function show(string $number, OrderReconciler $reconciler): JsonResponse
    {
        $order = Order::where('number', $number)->firstOrFail();

        // One question to DOKU per order every 20 seconds, however often the page asks.
        if (Cache::add('doku-status:'.$order->id, true, 20)) {
            $order = $reconciler->refresh($order);
        }

        return response()->json($this->payload($order));
    }

    /**
     * Lets the browser that created a paid order open the class straight away.
     * An email that already owned a class gets a mailed link instead: the form
     * accepts any address, so paying must not hand over an existing account.
     */
    public function access(Request $request, string $number, LoginLink $links): JsonResponse
    {
        $order = Order::where('number', $number)->firstOrFail();

        if (! $this->claimed($order, $request)) {
            return response()->json(['message' => 'Akses langsung tidak tersedia untuk pesanan ini. Masuk dengan emailmu.'], 403);
        }

        $student = $order->student;

        if ($this->ownedBefore($order)) {
            $links->send($student);

            return response()->json(['emailed' => true]);
        }

        return response()->json(['token' => $links->issue($student, 5)]);
    }

    /**
     * Shows the buying browser a system-made password, once. An account that
     * already existed keeps whatever it has: see access().
     */
    public function password(Request $request, string $number, PasswordIssuer $passwords): JsonResponse
    {
        $order = Order::where('number', $number)->firstOrFail();

        if (! $this->claimed($order, $request)) {
            return response()->json(['message' => 'Kata sandi tidak bisa dibuat dari sini. Masuk dengan emailmu.'], 403);
        }

        if ($this->ownedBefore($order)) {
            return response()->json(['password' => null, 'reason' => 'existing']);
        }

        if ($order->student->password !== null) {
            return response()->json(['password' => null, 'reason' => 'shown']);
        }

        return response()->json(['password' => $passwords->issue($order->student)]);
    }

    private function claimed(Order $order, Request $request): bool
    {
        $claim = (string) $request->input('claim');

        return $order->status === OrderStatus::Paid
            && $order->claim_hash !== null
            && $claim !== ''
            && hash_equals($order->claim_hash, hash('sha256', $claim))
            && (bool) $order->paid_at?->gt(now()->subHours(config('lms.claim_hours')));
    }

    /** Owned a class this order did not just open: a bundle's add-on is not "before". */
    private function ownedBefore(Order $order): bool
    {
        return $order->student->enrollments()
            ->whereNotIn('course_id', $order->purchasedCourses()->pluck('id'))
            ->exists();
    }

    private function payload(Order $order): array
    {
        return [
            'order' => $order->number,
            'course' => $order->course->slug,
            'status' => $order->status->value,
            'amount' => $order->amount,
            'items' => $order->items()->with('course')->get()->map(fn (OrderItem $item) => [
                'course' => $item->course->slug,
                'title' => $item->course->title,
                'tracking_id' => $item->course->trackingId(),
                'kind' => $item->kind,
                'price' => $item->price,
            ])->values()->all(),
            'payment_url' => $order->status->value === 'pending' ? $order->payment_url : null,
            'expires_at' => $order->expires_at?->toIso8601String(),
        ];
    }
}
