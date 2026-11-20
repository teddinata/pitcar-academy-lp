<?php

namespace App\Console\Commands;

use App\Enums\EnrollmentSource;
use App\Mail\ScalevMoveMail;
use App\Models\Student;
use App\Services\Lms\LoginLink;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * One email per buyer moved from Scalev, with a sign-in link. Each student
 * is emailed once; run it again after a later import and only the new ones
 * get it. Run --dry-run first to see who would.
 */
class AnnounceScalevMove extends Command
{
    protected $signature = 'scalev:announce
        {--dry-run : List who would get the email without sending}
        {--limit=0 : Send to at most this many students in this run}';

    protected $description = 'Email buyers moved from Scalev that their class now opens on the Pitcar Academy site';

    public function handle(LoginLink $links): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));
        $minutes = (int) config('lms.purchase_link_minutes');

        $query = Student::query()
            ->whereNull('scalev_announced_at')
            ->whereHas('enrollments', fn ($q) => $q->where('source', EnrollmentSource::Scalev)->whereNull('revoked_at'))
            ->with(['enrollments' => fn ($q) => $q->where('source', EnrollmentSource::Scalev)->whereNull('revoked_at')->with('course')])
            ->orderBy('id');

        $total = (clone $query)->count();
        $this->info(($dryRun ? 'Mode uji. ' : '')."{$total} pembeli dari Scalev belum dikirimi email.");

        if ($total === 0) {
            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;

        foreach ($query->when($limit > 0, fn ($q) => $q->limit($limit))->cursor() as $student) {
            $titles = $student->enrollments->pluck('course.title')->filter()->unique()->values()->all();

            if ($dryRun) {
                $this->line("- {$student->name} <{$student->email}>: ".implode(', ', $titles));

                continue;
            }

            try {
                $url = config('lms.frontend_url').'/belajar/masuk/?token='.$links->issue($student, $minutes);
                Mail::to($student->email)->send(new ScalevMoveMail($student, $titles, $url, $minutes));
                $student->forceFill(['scalev_announced_at' => now()])->save();
                $sent++;
            } catch (Throwable $e) {
                report($e);
                $failed++;
                $this->warn("Gagal ke {$student->email}: {$e->getMessage()}");
            }

            // Resend allows a few requests a second; stay well under it.
            usleep(600_000);
        }

        if (! $dryRun) {
            $this->info("Terkirim: {$sent}. Gagal: {$failed}.".($failed ? ' Jalankan lagi untuk mencoba yang gagal.' : ''));
        }

        return self::SUCCESS;
    }
}
