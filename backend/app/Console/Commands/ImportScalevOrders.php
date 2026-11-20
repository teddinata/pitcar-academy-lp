<?php

namespace App\Console\Commands;

use App\Services\Lms\ScalevImport;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * One pass over every Scalev order: paid buyers get their classes here,
 * unpaid orders from the last week go on the follow-up list. Safe to run
 * again; nothing is created twice. Sends nothing to buyers.
 */
class ImportScalevOrders extends Command
{
    protected $signature = 'scalev:import {--dry-run : Count what would change without writing anything}';

    protected $description = 'Move Scalev buyers into the LMS and list unpaid Scalev orders for follow-up';

    // 25 per page; 4000 pages is 100k orders, far past this shop's history.
    private const MAX_PAGES = 4000;

    private const LABELS = [
        'enrolled' => 'Pembeli dapat akses kelas',
        'owned' => 'Sudah punya kelasnya',
        'revoked' => 'Akses pernah dicabut (dibiarkan)',
        'no_email' => 'Lunas, tanpa email (tidak bisa masuk LMS)',
        'no_course' => 'Lunas, produk tidak cocok dengan kelas mana pun',
        'unpaid' => 'Belum bayar, masuk daftar follow-up',
        'unpaid_old' => 'Belum bayar, lebih dari 7 hari (dilewati)',
        'closed' => 'Dibatalkan atau retur',
        'spam' => 'Spam',
        'other' => 'Status lain (dilewati)',
    ];

    public function handle(ScalevImport $import): int
    {
        $apiKey = (string) config('services.scalev.api_key');

        if ($apiKey === '') {
            $this->error('SCALEV_API_KEY belum diisi di .env.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $counts = array_fill_keys(array_keys(self::LABELS), 0);
        $unmatched = [];
        $emails = [];
        $cursor = null;
        $total = 0;

        $this->info($dryRun ? 'Mode uji: tidak ada yang ditulis.' : 'Mengimpor pesanan Scalev...');

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $response = $this->fetch($apiKey, $cursor);

            if ($response->failed()) {
                $this->error("Scalev API membalas {$response->status()}: {$response->body()}");

                return self::FAILURE;
            }

            foreach ($response->json('data', []) as $order) {
                $total++;
                $result = $import->apply($order, $dryRun);
                $counts[$result] = ($counts[$result] ?? 0) + 1;

                if ($result === 'no_course') {
                    foreach ($import->productNames($order) ?: ['(tanpa nama produk)'] as $name) {
                        $unmatched[$name] = ($unmatched[$name] ?? 0) + 1;
                    }
                }

                if ($result === 'enrolled' && filled($order['customer']['email'] ?? null)) {
                    $emails[mb_strtolower(trim($order['customer']['email']))] = true;
                }
            }

            $this->output->write("\rDibaca: {$total} pesanan");

            if (! $response->json('has_next') || ! $response->json('next_cursor')) {
                break;
            }

            $cursor = $response->json('next_cursor');
        }

        $this->newLine(2);
        $this->table(['Hasil', 'Pesanan'], collect(self::LABELS)->map(fn ($label, $key) => [$label, $counts[$key]])->values()->all());
        $this->line('Pembeli unik yang '.($dryRun ? 'akan mendapat' : 'mendapat').' akses: '.count($emails));

        if ($unmatched !== []) {
            arsort($unmatched);
            $this->warn('Nama produk yang tidak cocok dengan kelas mana pun (atur di config/services.php, scalev.courses):');
            $this->table(['Produk', 'Pesanan'], collect($unmatched)->map(fn ($n, $name) => [$name, $n])->values()->all());
        }

        return self::SUCCESS;
    }

    /** One page, waiting out Scalev's rate limit instead of failing on it. */
    private function fetch(string $apiKey, ?string $cursor): Response
    {
        for ($attempt = 0; ; $attempt++) {
            $response = Http::baseUrl(rtrim((string) config('services.scalev.api_url'), '/'))
                ->timeout((int) config('services.scalev.timeout', 15))
                ->withToken($apiKey)
                ->get('/v3/orders', array_filter([
                    'page_size' => 25,
                    'columns' => ScalevImport::COLUMNS,
                    'next_cursor' => $cursor,
                ]));

            if ($response->status() !== 429 || $attempt >= 5) {
                return $response;
            }

            sleep(max(1, min(60, (int) $response->header('Retry-After') ?: 5 * ($attempt + 1))));
        }
    }
}
