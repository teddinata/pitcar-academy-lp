<?php

namespace App\Services\Analytics;

use App\Models\AdSpendDaily;
use App\Models\AppSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads spend, link clicks and purchases per campaign per day from the Meta
 * Marketing API (ads_read). The ad account and token are saved from the
 * admin (Pengaturan › Iklan Meta).
 */
class MetaAds
{
    public const ACCOUNT = 'meta_ads.account_id';

    public const TOKEN = 'meta_ads.token';

    public const SYNCED_AT = 'meta_ads.synced_at';

    public const LAST_ERROR = 'meta_ads.last_error';

    /** Meta reports one purchase under several names; the first one present is counted. */
    private const PURCHASE_TYPES = ['omni_purchase', 'offsite_conversion.fb_pixel_purchase', 'purchase'];

    public function configured(): bool
    {
        return $this->account() !== null && $this->token() !== null;
    }

    /** Digits only; "act_" is added when calling Meta. */
    public function account(): ?string
    {
        $id = preg_replace('/\D/', '', (string) AppSetting::get(self::ACCOUNT));

        return $id === '' ? null : $id;
    }

    public function token(): ?string
    {
        return AppSetting::secret(self::TOKEN);
    }

    /** @return array{name: string, currency: string} */
    public function check(): array
    {
        $data = $this->get("act_{$this->account()}", ['fields' => 'name,currency,account_status']);

        if ((int) ($data['account_status'] ?? 0) !== 1) {
            throw new RuntimeException('Akun iklan ditemukan, tapi statusnya tidak aktif di Meta (kode '.($data['account_status'] ?? '?').').');
        }

        return ['name' => (string) ($data['name'] ?? ''), 'currency' => (string) ($data['currency'] ?? '')];
    }

    /** Pulls the given days and replaces what is stored for them. Returns the rows written. */
    public function sync(CarbonImmutable $from, CarbonImmutable $to): int
    {
        if (! $this->configured()) {
            throw new RuntimeException('Akun iklan Meta belum diatur.');
        }

        try {
            $currency = $this->check()['currency'] ?: 'IDR';
            $rows = [];
            $params = [
                'level' => 'campaign',
                'time_increment' => 1,
                'time_range' => json_encode(['since' => $from->toDateString(), 'until' => $to->toDateString()]),
                'fields' => 'campaign_id,campaign_name,spend,impressions,inline_link_clicks,actions',
                'limit' => 500,
            ];
            $page = $this->get("act_{$this->account()}/insights", $params);

            // Meta pages the result; a 90-day pull of many campaigns can take a few pages.
            for ($i = 0; $i < 50; $i++) {
                foreach ($page['data'] ?? [] as $row) {
                    $rows[] = $this->row($row, $currency);
                }
                $next = $page['paging']['next'] ?? null;
                if (! $next) {
                    break;
                }
                $page = $this->fetch(Http::timeout(30)->get($next));
            }

            AdSpendDaily::whereBetween('date', [$from->toDateString(), $to->toDateString()])->delete();
            foreach (array_chunk($rows, 200) as $chunk) {
                AdSpendDaily::insert($chunk);
            }

            AppSetting::put(self::SYNCED_AT, now()->toIso8601String());
            AppSetting::put(self::LAST_ERROR, null);

            return count($rows);
        } catch (RuntimeException $e) {
            AppSetting::put(self::LAST_ERROR, $e->getMessage());

            throw $e;
        }
    }

    private function row(array $row, string $currency): array
    {
        $actions = collect($row['actions'] ?? [])->pluck('value', 'action_type');
        $type = collect(self::PURCHASE_TYPES)->first(fn ($t) => $actions->has($t));

        return [
            'date' => $row['date_start'],
            'campaign_id' => (string) $row['campaign_id'],
            'campaign_name' => mb_substr((string) ($row['campaign_name'] ?? ''), 0, 255),
            'spend' => (float) ($row['spend'] ?? 0),
            'impressions' => (int) ($row['impressions'] ?? 0),
            'link_clicks' => (int) ($row['inline_link_clicks'] ?? 0),
            'purchases' => $type ? (int) $actions[$type] : 0,
            'currency' => $currency,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function get(string $path, array $params): array
    {
        $version = config('services.meta.graph_version', 'v21.0');

        return $this->fetch(Http::timeout(30)->get("https://graph.facebook.com/{$version}/{$path}", [...$params, 'access_token' => $this->token()]));
    }

    private function fetch($response): array
    {
        if ($response->successful()) {
            return $response->json() ?? [];
        }

        $error = $response->json('error') ?? [];
        $code = (int) ($error['code'] ?? 0);

        throw new RuntimeException(match (true) {
            $code === 190 => 'Token Meta tidak berlaku atau sudah kedaluwarsa. Buat token baru lalu simpan lagi.',
            in_array($code, [10, 200, 294], true) || str_contains((string) ($error['message'] ?? ''), 'ads_read') => 'Token belum punya izin ads_read untuk akun iklan ini.',
            $code === 100 => 'Ad Account ID tidak ditemukan atau tidak bisa diakses token ini.',
            in_array($code, [4, 17, 32, 613], true) => 'Meta membatasi jumlah permintaan. Coba lagi beberapa menit lagi.',
            default => 'Meta menolak permintaan: '.($error['message'] ?? 'HTTP '.$response->status()),
        });
    }
}
