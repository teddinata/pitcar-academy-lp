<?php

namespace App\Services\Lms;

use App\Enums\OrderStatus;
use App\Models\Course;
use App\Models\CourseOffer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Student;
use App\Services\Doku\DokuCheckout;
use App\Services\Doku\DokuException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Checkout
{
    public function __construct(private DokuCheckout $doku) {}

    /**
     * Returns an order the buyer can pay, and the claim that proves this
     * browser created it. A browser that resubmits with its claim gets the
     * payment page it already has instead of a second invoice.
     *
     * Add-ons are the slugs of courses this course offers at checkout. Their
     * prices come from the offer rows, never from the request.
     *
     * @param  array{name: string, email: string, whatsapp: string, order?: ?string, claim?: ?string}  $buyer
     * @param  list<string>  $addons
     * @param  array<string, mixed>  $attribution
     * @return array{order: Order, claim: string}
     */
    public function start(Course $course, array $buyer, array $addons = [], array $attribution = []): array
    {
        $offers = $this->offers($course, $addons);

        $student = Student::firstOrNew(['email' => mb_strtolower(trim($buyer['email']))]);

        // Anyone can type someone else's email here, so an account that already owns a class keeps its details.
        if (! $student->exists || ! $student->enrollments()->exists()) {
            $student->fill(['name' => $buyer['name'], 'whatsapp' => self::phone($buyer['whatsapp'])])->save();
        }

        if ($student->hasAccessTo($course)) {
            throw new AlreadyEnrolled;
        }

        foreach ($offers as $offer) {
            if ($student->hasAccessTo($offer->offerCourse)) {
                throw new AddonUnavailable('Email ini sudah punya '.$offer->offerCourse->title.'. Hapus centang tambahan itu untuk lanjut.', owned: true);
            }
        }

        $amount = $course->price + $offers->sum('price');
        $courseIds = $offers->pluck('offer_course_id')->push($course->id)->sort()->values()->all();
        $claim = (string) ($buyer['claim'] ?? '');

        $open = filled($buyer['order'] ?? null) && $claim !== ''
            ? Order::with('items')
                ->where('number', $buyer['order'])
                ->where('student_id', $student->id)
                ->where('course_id', $course->id)
                ->where('status', OrderStatus::Pending)
                ->where('amount', $amount)
                ->where('claim_hash', hash('sha256', $claim))
                ->whereNotNull('payment_url')
                ->where('expires_at', '>', now()->addMinutes(5))
                ->first()
            : null;

        // Same buyer, same basket, same price: keep the payment page they already have.
        if ($open && $open->items->pluck('course_id')->sort()->values()->all() === $courseIds) {
            return ['order' => $open, 'claim' => $claim];
        }

        $claim = Str::random(48);

        $order = DB::transaction(function () use ($student, $course, $offers, $amount, $claim, $attribution) {
            $order = Order::create([
                'number' => self::number(),
                'student_id' => $student->id,
                'course_id' => $course->id,
                'amount' => $amount,
                'status' => OrderStatus::Pending,
                'gateway' => 'doku',
                'claim_hash' => hash('sha256', $claim),
                'attribution' => $attribution ?: null,
            ]);

            $order->items()->create(['course_id' => $course->id, 'kind' => OrderItem::MAIN, 'price' => $course->price]);

            foreach ($offers as $offer) {
                $order->items()->create([
                    'course_id' => $offer->offer_course_id,
                    'course_offer_id' => $offer->id,
                    'kind' => OrderItem::ADDON,
                    'price' => $offer->price,
                ]);
            }

            return $order;
        });

        try {
            $payment = $this->doku->createPayment($order);
        } catch (DokuException $e) {
            // No payment page exists for it, so the buyer's next attempt starts a new invoice.
            $order->update(['status' => OrderStatus::Failed]);

            throw $e;
        }

        $order->update(['payment_url' => $payment['url'], 'expires_at' => $payment['expires_at']]);
        app(TeamAlerts::class)->newOrder($order);

        return ['order' => $order, 'claim' => $claim];
    }

    /**
     * The offers matching the requested add-on slugs, each checked against
     * what this course actually offers right now.
     *
     * @param  list<string>  $slugs
     * @return Collection<int, CourseOffer>
     */
    private function offers(Course $course, array $slugs): Collection
    {
        $slugs = array_values(array_unique(array_filter($slugs, 'is_string')));

        if ($slugs === []) {
            return collect();
        }

        $offers = $course->offers()->available()->with('offerCourse')->get()
            ->filter(fn (CourseOffer $offer) => in_array($offer->offerCourse->slug, $slugs, true))
            ->values();

        if ($offers->count() !== count($slugs)) {
            throw new AddonUnavailable('Penawaran tambahan ini sudah tidak tersedia. Muat ulang halaman, lalu coba lagi.');
        }

        return $offers;
    }

    // Letters and digits only: some DOKU channels reject symbols in an invoice number.
    private static function number(): string
    {
        do {
            $number = 'PA'.now()->format('ymd').Str::upper(Str::random(8));
        } while (Order::where('number', $number)->exists());

        return $number;
    }

    /** 0812... and +62 812... both become 62812... */
    public static function phone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw);

        return str_starts_with($digits, '0') ? '62'.substr($digits, 1) : $digits;
    }
}
