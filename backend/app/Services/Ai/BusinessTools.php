<?php

namespace App\Services\Ai;

use App\Enums\OrderStatus;
use App\Models\Course;
use App\Models\CourseReview;
use App\Models\DiscussionPost;
use App\Models\LessonProgress;
use App\Models\Order;
use App\Models\Student;
use App\Models\StudentRequest;
use App\Services\Analytics\OnlineReport;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Read-only questions about the online-class business, for the admin's AI
 * and the MCP server. Every tool returns plain data as JSON; none writes.
 * Money is in rupiah, times are in Jakarta time.
 */
class BusinessTools
{
    /** @return list<array{name: string, description: string, inputSchema: array<string, mixed>}> */
    public function definitions(): array
    {
        $range = [
            'from' => ['type' => 'string', 'description' => 'Tanggal awal YYYY-MM-DD. Kosong: 30 hari terakhir.'],
            'to' => ['type' => 'string', 'description' => 'Tanggal akhir YYYY-MM-DD. Kosong: hari ini.'],
        ];
        $limit = ['type' => 'integer', 'description' => 'Jumlah baris maksimal, 1-50. Bawaan 20.'];
        $course = ['type' => 'string', 'description' => 'Slug atau sebagian judul kelas. Kosong: semua kelas.'];

        return [
            $this->def('courses', 'Daftar kelas online: harga, jumlah siswa aktif, jumlah video, nilai rata-rata dari siswa.', []),
            $this->def('sales_summary', 'Ringkasan penjualan dan iklan dalam rentang tanggal: pengunjung, penjualan (DOKU lunas + Scalev selesai), omzet, biaya iklan Meta, biaya per penjualan, ROAS, konversi. Juga rincian penjualan DOKU per kelas.', $range),
            $this->def('traffic', 'Asal pengunjung (sumber), halaman paling banyak dibuka, dan kinerja kampanye iklan Meta dalam rentang tanggal.', $range),
            $this->def('orders', 'Pesanan DOKU terbaru. status "paid" untuk yang lunas, "unpaid" untuk yang belum bayar (calon follow-up).', [
                'status' => ['type' => 'string', 'enum' => ['paid', 'unpaid'], 'description' => 'paid atau unpaid.'],
                'days' => ['type' => 'integer', 'description' => 'Berapa hari ke belakang, 1-90. Bawaan 7.'],
                'limit' => $limit,
            ], ['status']),
            $this->def('find_student', 'Cari siswa dari nama, email, atau nomor WhatsApp. Hasil: kelas yang dimiliki, progres per kelas, pesanan, terakhir masuk.', [
                'query' => ['type' => 'string', 'description' => 'Nama, email, atau nomor WhatsApp (sebagian juga boleh).'],
            ], ['query']),
            $this->def('course_students', 'Siswa satu kelas beserta progresnya. filter "not_started" untuk yang belum menonton sama sekali, "finished" untuk yang sudah 90% ke atas.', [
                'course' => ['type' => 'string', 'description' => 'Slug atau sebagian judul kelas.'],
                'filter' => ['type' => 'string', 'enum' => ['all', 'not_started', 'finished'], 'description' => 'Bawaan all.'],
                'limit' => $limit,
            ], ['course']),
            $this->def('discussions', 'Diskusi kelas. filter "unanswered" untuk pertanyaan yang belum dijawab tim, "reported" untuk pesan yang dilaporkan siswa, "recent" untuk yang terbaru.', [
                'filter' => ['type' => 'string', 'enum' => ['unanswered', 'reported', 'recent'], 'description' => 'Bawaan unanswered.'],
                'course' => $course,
                'limit' => $limit,
            ]),
            $this->def('student_feedback', 'Masukan siswa. kind "requests" untuk pesan lewat menu Masukan (usul materi, kendala), "reviews" untuk nilai dan ulasan kelas.', [
                'kind' => ['type' => 'string', 'enum' => ['requests', 'reviews'], 'description' => 'requests atau reviews.'],
                'course' => $course,
                'only_open' => ['type' => 'boolean', 'description' => 'Untuk requests: hanya yang belum selesai ditanggapi.'],
                'limit' => $limit,
            ], ['kind']),
        ];
    }

    /** Runs one tool and returns its result as JSON. */
    public function run(string $name, array $input): string
    {
        $result = match ($name) {
            'courses' => $this->courses(),
            'sales_summary' => $this->salesSummary($input),
            'traffic' => $this->traffic($input),
            'orders' => $this->orders($input),
            'find_student' => $this->findStudent($input),
            'course_students' => $this->courseStudents($input),
            'discussions' => $this->discussions($input),
            'student_feedback' => $this->feedback($input),
            default => throw new InvalidArgumentException("Alat {$name} tidak dikenal."),
        };

        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function courses(): array
    {
        return Course::query()->orderBy('id')->get()->map(fn (Course $c) => [
            'slug' => $c->slug,
            'title' => $c->title,
            'status' => $c->status?->value ?? $c->status,
            'price' => (int) $c->price,
            'students' => $c->enrollments()->whereNull('revoked_at')->count(),
            'videos' => $c->lessons()->count(),
            'rating' => ($avg = CourseReview::where('course_id', $c->id)->avg('rating')) ? round((float) $avg, 2) : null,
            'ratings' => CourseReview::where('course_id', $c->id)->count(),
        ])->all();
    }

    private function salesSummary(array $in): array
    {
        $report = $this->report($in);
        $paid = Order::where('status', OrderStatus::Paid)->whereBetween('paid_at', [$report->from->utc(), $report->to->utc()])->with('course')->get();

        return [
            'range' => $this->rangeLabel($report),
            'summary' => $report->summary(),
            'doku_by_course' => $paid->groupBy(fn (Order $o) => $o->course?->title ?? 'Lainnya')
                ->map(fn ($orders, $title) => ['course' => $title, 'sales' => $orders->count(), 'revenue' => (int) $orders->sum('amount')])
                ->values()->all(),
            'note' => 'sales dan revenue di summary = DOKU lunas + Scalev selesai. spend = biaya iklan Meta (0 kalau Meta belum terhubung).',
        ];
    }

    private function traffic(array $in): array
    {
        $report = $this->report($in);

        return [
            'range' => $this->rangeLabel($report),
            'sources' => $report->sources(),
            'pages' => $report->pages(10),
            'campaigns' => $report->campaigns(),
        ];
    }

    private function orders(array $in): array
    {
        $days = $this->clamp($in['days'] ?? 7, 1, 90);
        $query = ($in['status'] ?? 'paid') === 'unpaid'
            ? Order::unpaid()->where('created_at', '>=', now()->subDays($days))
            : Order::where('status', OrderStatus::Paid)->where('paid_at', '>=', now()->subDays($days));

        return $query->with(['student', 'course'])->latest()->limit($this->limit($in))->get()->map(fn (Order $o) => [
            'number' => $o->number,
            'buyer' => $o->student?->name,
            'email' => $o->student?->email,
            'whatsapp' => $o->student?->whatsapp,
            'course' => $o->purchaseTitle(),
            'amount' => (int) $o->amount,
            'status' => $o->status->value,
            'ordered_at' => $this->time($o->created_at),
            'paid_at' => $this->time($o->paid_at),
            'followed_up_at' => $this->time($o->followed_up_at),
        ])->all();
    }

    private function findStudent(array $in): array
    {
        $q = trim((string) ($in['query'] ?? ''));

        if (mb_strlen($q) < 3) {
            return ['error' => 'Isi minimal 3 huruf atau angka.'];
        }

        $digits = preg_replace('/\D/', '', $q);

        return Student::query()
            ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', '%'.mb_strtolower($q).'%')
                ->when(strlen($digits) >= 6, fn ($x) => $x->orWhere('whatsapp', 'like', '%'.ltrim($digits, '0').'%')))
            ->with(['enrollments.course', 'orders.course'])
            ->limit(10)->get()
            ->map(fn (Student $s) => [
                'name' => $s->name,
                'email' => $s->email,
                'whatsapp' => $s->whatsapp,
                'last_login_at' => $this->time($s->last_login_at),
                'classes' => $s->enrollments->map(fn ($e) => [
                    'course' => $e->course?->title,
                    'source' => $e->source?->value,
                    'since' => $this->time($e->granted_at),
                    'revoked' => $e->revoked_at !== null,
                    'progress_percent' => $e->course ? $this->percent($s, $e->course) : null,
                ])->all(),
                'orders' => $s->orders->map(fn (Order $o) => ['number' => $o->number, 'course' => $o->course?->title, 'amount' => (int) $o->amount, 'status' => $o->status->value, 'at' => $this->time($o->created_at)])->all(),
            ])->all();
    }

    private function courseStudents(array $in): array
    {
        $course = $this->course($in['course'] ?? null);

        if ($course === null) {
            return ['error' => 'Kelas tidak ditemukan. Pakai alat courses untuk melihat daftar kelas.'];
        }

        $filter = $in['filter'] ?? 'all';
        $rows = $course->enrollments()->whereNull('revoked_at')->with('student')->latest('granted_at')->get()
            ->map(fn ($e) => ['student' => $e->student, 'since' => $e->granted_at, 'source' => $e->source?->value])
            ->filter(fn ($r) => $r['student'] !== null)
            ->map(fn ($r) => $r + ['percent' => $this->percent($r['student'], $course)])
            ->filter(fn ($r) => match ($filter) {
                'not_started' => $r['percent'] === 0,
                'finished' => $r['percent'] >= 90,
                default => true,
            })
            ->values();

        return [
            'course' => $course->title,
            'matching' => $rows->count(),
            'students' => $rows->take($this->limit($in))->map(fn ($r) => [
                'name' => $r['student']->name,
                'email' => $r['student']->email,
                'whatsapp' => $r['student']->whatsapp,
                'source' => $r['source'],
                'since' => $this->time($r['since']),
                'progress_percent' => $r['percent'],
                'last_login_at' => $this->time($r['student']->last_login_at),
            ])->all(),
        ];
    }

    private function discussions(array $in): array
    {
        $course = filled($in['course'] ?? null) ? $this->course($in['course']) : null;
        $query = match ($in['filter'] ?? 'unanswered') {
            'reported' => DiscussionPost::reported()->withCount(['reports' => fn ($q) => $q->whereNull('resolved_at')]),
            'recent' => DiscussionPost::threads()->visible(),
            default => DiscussionPost::unanswered(),
        };

        return $query->when($course, fn ($q) => $q->where('course_id', $course->id))
            ->with(['course', 'lesson', 'student', 'user', 'thread'])
            ->latest()->limit($this->limit($in))->get()
            ->map(fn (DiscussionPost $p) => array_filter([
                'id' => $p->id,
                'course' => $p->course?->title,
                'video' => $p->lesson?->title,
                'author' => $p->authorName(),
                'by_team' => $p->user_id !== null,
                'body' => $p->body,
                'in_thread' => $p->isThread() ? null : str($p->thread?->body)->limit(140)->toString(),
                'replies' => $p->isThread() ? $p->reply_count : null,
                'open_reports' => $p->reports_count ?? null,
                'at' => $this->time($p->created_at),
            ], fn ($v) => $v !== null))->all();
    }

    private function feedback(array $in): array
    {
        $course = filled($in['course'] ?? null) ? $this->course($in['course']) : null;

        if (($in['kind'] ?? 'requests') === 'reviews') {
            return CourseReview::query()->when($course, fn ($q) => $q->where('course_id', $course->id))
                ->with(['course', 'student'])->latest()->limit($this->limit($in))->get()
                ->map(fn (CourseReview $r) => ['course' => $r->course?->title, 'student' => $r->student?->name, 'rating' => $r->rating, 'review' => $r->body, 'may_publish' => (bool) $r->may_publish, 'at' => $this->time($r->updated_at)])
                ->all();
        }

        return StudentRequest::query()->when($course, fn ($q) => $q->where('course_id', $course->id))
            ->when($in['only_open'] ?? false, fn ($q) => $q->where('status', '!=', 'done'))
            ->with(['course', 'student'])->latest()->limit($this->limit($in))->get()
            ->map(fn (StudentRequest $r) => ['kind' => $r->kind->label(), 'status' => $r->status->label(), 'course' => $r->course?->title, 'student' => $r->student?->name, 'message' => $r->body, 'team_reply' => $r->reply, 'at' => $this->time($r->created_at)])
            ->all();
    }

    private function def(string $name, string $description, array $properties, array $required = []): array
    {
        return [
            'name' => $name,
            'description' => $description,
            // An empty properties list would serialize as [] instead of {}; leave it out.
            'inputSchema' => array_filter([
                'type' => 'object',
                'properties' => $properties ?: null,
                'required' => $required ?: null,
            ], fn ($v) => $v !== null),
        ];
    }

    private function report(array $in): OnlineReport
    {
        $tz = config('app.display_timezone');
        $to = $this->date($in['to'] ?? null) ?? CarbonImmutable::now($tz);
        $from = $this->date($in['from'] ?? null) ?? $to->subDays(29);

        return new OnlineReport($from->min($to), $to);
    }

    private function rangeLabel(OnlineReport $report): string
    {
        return $report->from->toDateString().' s.d. '.$report->to->toDateString();
    }

    private function date(?string $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $value, config('app.display_timezone')) ?: null;
    }

    private function course(?string $needle): ?Course
    {
        $needle = trim((string) $needle);

        return $needle === '' ? null : (Course::where('slug', $needle)->first() ?? Course::where('title', 'like', "%{$needle}%")->first());
    }

    private function percent(Student $student, Course $course): int
    {
        $ids = $course->lessons()->pluck('lessons.id');

        return $ids->isEmpty() ? 0 : (int) round(LessonProgress::where('student_id', $student->id)->whereIn('lesson_id', $ids)->sum('watched_percent') / $ids->count());
    }

    private function time($value): ?string
    {
        return $value ? CarbonImmutable::parse($value)->setTimezone(config('app.display_timezone'))->format('Y-m-d H:i') : null;
    }

    private function limit(array $in): int
    {
        return $this->clamp($in['limit'] ?? 20, 1, 50);
    }

    private function clamp(mixed $value, int $min, int $max): int
    {
        return max($min, min($max, (int) $value));
    }
}
