<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\CourseOffer;
use App\Services\Doku\DokuCheckout;
use App\Services\Doku\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Checks, before any buyer pays, that the payment setup on this server works:
 * the config, DOKU accepting our keys, and DOKU's notifications being able to
 * reach this app over its public address and pass the signature check.
 *
 * Creates no order and charges nothing. The test notification names an
 * invoice that does not exist, so the app answers "Unknown order" (404),
 * which is the proof that it got through.
 */
class CheckDokuSetup extends Command
{
    protected $signature = 'lms:doku-check {--offline : Only check the config, without calling DOKU or the public URL}';

    protected $description = 'Check DOKU payment setup and that DOKU notifications can reach this server';

    /** @var list<array{string, string, string}> */
    private array $rows = [];

    private bool $failed = false;

    public function handle(DokuCheckout $doku): int
    {
        $base = (string) config('doku.base_url');
        $appUrl = rtrim((string) config('app.url'), '/');
        $notifyUrl = $appUrl.config('doku.notification_path');
        $production = app()->environment('production');
        $sandbox = str_contains($base, 'sandbox') || str_contains($base, 'staging');

        $this->row(filled(config('doku.client_id')) && filled(config('doku.secret_key')) ? 'ok' : 'fail',
            'Kunci DOKU', $doku->configured() ? 'Client ID dan Secret Key terisi' : 'DOKU_CLIENT_ID atau DOKU_SECRET_KEY kosong');
        $this->row($production && $sandbox ? 'warn' : 'ok',
            'Lingkungan DOKU', ($sandbox ? 'sandbox' : 'produksi').' ('.$base.')'.($production && $sandbox ? ': server produksi masih memakai sandbox' : ''));
        $this->row(str_starts_with($appUrl, 'https://') && ! Str::contains($appUrl, ['localhost', '127.0.0.1']) ? 'ok' : 'fail',
            'APP_URL', $appUrl.' (DOKU butuh alamat HTTPS publik)');
        $this->row('ok', 'URL notifikasi', $notifyUrl);
        $this->row(str_starts_with((string) config('doku.return_url'), 'https://') ? 'ok' : 'warn',
            'DOKU_RETURN_URL', (string) (config('doku.return_url') ?: 'kosong'));
        $this->row(str_starts_with((string) config('lms.frontend_url'), 'https://') ? 'ok' : 'warn',
            'LMS_FRONTEND_URL', (string) config('lms.frontend_url'));
        $this->row(config('mail.default') === 'log' ? 'warn' : 'ok',
            'Email', 'MAIL_MAILER='.config('mail.default').(config('mail.default') === 'log' ? ': email akses tidak benar-benar terkirim' : ''));
        $this->row(config('queue.default') === 'sync' ? 'warn' : 'ok',
            'Queue', config('queue.default').': pastikan pitcar-worker berjalan');
        $this->row(filled(config('services.meta.access_token')) ? 'ok' : 'warn',
            'Meta CAPI', filled(config('services.meta.access_token')) ? 'token terisi' : 'META_CAPI_ACCESS_TOKEN kosong: Purchase hanya dari browser');

        $courses = Course::count();
        $offers = CourseOffer::where('is_active', true)->count();
        $this->row($courses > 0 ? 'ok' : 'fail', 'Data kelas', "{$courses} kelas, {$offers} tawaran bundle aktif".($courses === 0 ? ': jalankan LmsCourseSeeder' : ''));

        if (! $this->option('offline') && $doku->configured()) {
            $this->checkKeys($doku);
            $this->checkNotificationRoute($notifyUrl);
        }

        $this->table(['', 'Pemeriksaan', 'Hasil'], $this->rows);

        if ($this->failed) {
            $this->error('Ada yang belum siap. Perbaiki baris bertanda GAGAL, lalu jalankan php artisan config:cache dan ulangi.');

            return self::FAILURE;
        }

        $this->info('Siap menerima pembayaran.');

        return self::SUCCESS;
    }

    private function checkKeys(DokuCheckout $doku): void
    {
        try {
            $probe = $doku->probe();
        } catch (Throwable $e) {
            $this->row('fail', 'DOKU menerima kunci', 'tidak bisa menghubungi DOKU: '.Str::limit($e->getMessage(), 120));

            return;
        }

        match (true) {
            $probe['code'] === 'data_not_found' => $this->row('ok', 'DOKU menerima kunci', 'ya (uji status invoice fiktif dijawab "tidak ditemukan")'),
            $probe['code'] === 'invalid_signature' => $this->row('fail', 'DOKU menerima kunci', 'ditolak: Secret Key tidak cocok dengan Client ID, atau kunci sandbox dipakai ke produksi'),
            default => $this->row('warn', 'DOKU menerima kunci', "jawaban tak terduga: HTTP {$probe['status']} ".($probe['code'] ?? '')),
        };
    }

    /**
     * Sends ourselves the kind of request DOKU sends, signed the way DOKU signs,
     * through the public address. Proves DNS, HTTPS, nginx, routing and the
     * signature check line up without a real payment.
     */
    private function checkNotificationRoute(string $url): void
    {
        $clientId = (string) config('doku.client_id');
        $body = json_encode([
            'order' => ['invoice_number' => 'DOKUCHECK'.Str::upper(Str::random(8)), 'amount' => 0],
            'transaction' => ['status' => 'SUCCESS'],
        ]);
        $requestId = (string) Str::uuid();
        $timestamp = now()->utc()->format('Y-m-d\TH:i:s\Z');

        try {
            $response = Http::withHeaders([
                'Client-Id' => $clientId,
                'Request-Id' => $requestId,
                'Request-Timestamp' => $timestamp,
                'Signature' => Signature::make($clientId, $requestId, $timestamp, (string) config('doku.notification_path'), $body, (string) config('doku.secret_key')),
            ])->withBody($body, 'application/json')->timeout(15)->post($url);
        } catch (Throwable $e) {
            $this->row('fail', 'Notifikasi sampai ke server', 'URL tidak bisa dihubungi: '.Str::limit($e->getMessage(), 120));

            return;
        }

        match ($response->status()) {
            404 => $this->row('ok', 'Notifikasi sampai ke server', 'ya: URL terjangkau, tanda tangan cocok, invoice uji dijawab "Unknown order"'),
            401 => $this->row('fail', 'Notifikasi sampai ke server', 'sampai, tapi tanda tangan ditolak: kunci di .env berbeda dengan yang dipakai aplikasi (jalankan config:cache)'),
            default => $this->row('fail', 'Notifikasi sampai ke server', "jawaban HTTP {$response->status()}: cek nginx dan APP_URL"),
        };
    }

    private function row(string $state, string $check, string $result): void
    {
        $this->failed = $this->failed || $state === 'fail';
        $this->rows[] = [match ($state) { 'ok' => 'OK', 'warn' => 'CEK', default => 'GAGAL' }, $check, $result];
    }
}
