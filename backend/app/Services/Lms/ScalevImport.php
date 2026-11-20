<?php

namespace App\Services\Lms;

use App\Enums\EnrollmentSource;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ScalevUnpaidOrder;
use App\Models\Student;
use App\Support\WhatsAppNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Brings Scalev orders into the LMS. A paid order opens its classes for the
 * buyer's email, quietly: the team announces the move itself. An unpaid one
 * from the last week goes on the follow-up list; once it is paid or
 * cancelled it leaves the list.
 */
class ScalevImport
{
    /** Columns of GET /v3/orders this needs. */
    public const COLUMNS = 'order_id,created_at,confirmed_time,completed_time,payment_status,status,gross_revenue,final_variants,customer,destination_address,is_probably_spam';

    /** @var array<int, array{id: int, patterns: list<string>}>|null */
    private ?array $courses = null;

    /**
     * What happened to the order, one of: enrolled, owned (had every class
     * already), revoked (access was taken away here; left alone), no_email,
     * no_course, unpaid, unpaid_old, closed, spam, other.
     */
    public function apply(array $order, bool $dryRun = false): string
    {
        $id = (string) ($order['order_id'] ?? $order['id'] ?? '');

        if ($id === '' || ($order['is_probably_spam'] ?? false) === true) {
            return 'spam';
        }

        $kind = $this->kind($order);

        if ($kind !== 'unpaid' && ! $dryRun) {
            ScalevUnpaidOrder::whereKey($id)->delete();
        }

        return match ($kind) {
            'paid' => $this->enroll($order, $dryRun),
            'unpaid' => $this->keepUnpaid($id, $order, $dryRun),
            default => $kind,
        };
    }

    /** paid, unpaid, closed (cancelled or returned before paying) or other. */
    public function kind(array $order): string
    {
        $status = $order['status'] ?? null;
        $payment = $order['payment_status'] ?? null;

        return match (true) {
            in_array($status, ['canceled', 'rts'], true) => 'closed',
            $status === 'completed', in_array($payment, ['paid', 'settled'], true) => 'paid',
            $status === 'closed' => 'closed',
            in_array($status, ['draft', 'pending'], true) && $payment === 'unpaid' => 'unpaid',
            default => 'other',
        };
    }

    /** @return list<string> the variant names on the order */
    public function productNames(array $order): array
    {
        $variants = $order['final_variants'] ?? [];

        return is_array($variants) ? array_map('strval', array_keys($variants)) : [];
    }

    /** @return list<int> the LMS classes the order's variants name */
    public function courseIds(array $order): array
    {
        $ids = [];

        foreach ($this->productNames($order) as $name) {
            foreach ($this->courses() as $course) {
                foreach ($course['patterns'] as $pattern) {
                    if ($pattern !== '' && mb_stripos($name, $pattern) !== false) {
                        $ids[$course['id']] = $course['id'];
                    }
                }
            }
        }

        return array_values($ids);
    }

    private function enroll(array $order, bool $dryRun): string
    {
        $email = $this->email($order);
        $courseIds = $this->courseIds($order);

        if ($email === null) {
            return 'no_email';
        }

        if ($courseIds === []) {
            return 'no_course';
        }

        $student = Student::where('email', $email)->first();
        $have = $student
            ? Enrollment::where('student_id', $student->id)->whereIn('course_id', $courseIds)->get()
            : collect();
        $missing = array_values(array_diff($courseIds, $have->pluck('course_id')->all()));

        if ($missing === []) {
            return $have->contains(fn (Enrollment $e) => $e->revoked_at === null) ? 'owned' : 'revoked';
        }

        if ($dryRun) {
            return 'enrolled';
        }

        DB::transaction(function () use ($order, $email, $student, $missing) {
            $student ??= Student::create([
                'name' => $this->name($order),
                'email' => $email,
                'whatsapp' => $this->whatsapp($order),
            ]);

            if ($student->whatsapp === null && ($wa = $this->whatsapp($order)) !== null) {
                $student->update(['whatsapp' => $wa]);
            }

            $granted = $this->time($order['completed_time'] ?? $order['confirmed_time'] ?? $order['created_at'] ?? null) ?? now();

            foreach ($missing as $courseId) {
                Enrollment::create([
                    'student_id' => $student->id,
                    'course_id' => $courseId,
                    'source' => EnrollmentSource::Scalev,
                    'granted_at' => $granted,
                ]);
            }
        });

        return 'enrolled';
    }

    private function keepUnpaid(string $id, array $order, bool $dryRun): string
    {
        $ordered = $this->time($order['created_at'] ?? null);

        if ($ordered === null || $ordered->lt(now()->subDays(ScalevUnpaidOrder::KEEP_DAYS))) {
            return 'unpaid_old';
        }

        if (! $dryRun) {
            ScalevUnpaidOrder::updateOrCreate(['order_id' => $id], [
                'customer_name' => $this->name($order),
                'email' => $this->email($order),
                'whatsapp' => $this->whatsapp($order),
                'product_name' => $this->productNames($order)[0] ?? null,
                'course_id' => $this->courseIds($order)[0] ?? null,
                'amount' => isset($order['gross_revenue']) ? (int) round((float) $order['gross_revenue']) : null,
                'ordered_at' => $ordered,
            ]);
        }

        return 'unpaid';
    }

    /** @return array<int, array{id: int, patterns: list<string>}> */
    private function courses(): array
    {
        if ($this->courses === null) {
            $ids = Course::whereIn('slug', array_keys((array) config('services.scalev.courses', [])))->pluck('id', 'slug');
            $this->courses = [];

            foreach ((array) config('services.scalev.courses', []) as $slug => $patterns) {
                if (isset($ids[$slug])) {
                    $this->courses[] = ['id' => (int) $ids[$slug], 'patterns' => array_values((array) $patterns)];
                }
            }
        }

        return $this->courses;
    }

    private function email(array $order): ?string
    {
        $email = mb_strtolower(trim((string) ($order['customer']['email'] ?? '')));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? substr($email, 0, 190) : null;
    }

    private function name(array $order): string
    {
        $name = trim((string) ($order['customer']['name'] ?? $order['destination_address']['name'] ?? ''));

        return $name === '' ? 'Pembeli' : mb_substr($name, 0, 120);
    }

    private function whatsapp(array $order): ?string
    {
        return WhatsAppNumber::normalize($order['customer']['phone'] ?? $order['destination_address']['phone'] ?? null);
    }

    private function time(mixed $value): ?CarbonImmutable
    {
        try {
            return filled($value) ? CarbonImmutable::parse((string) $value) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
