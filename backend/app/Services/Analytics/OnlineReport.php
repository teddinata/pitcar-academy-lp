<?php

namespace App\Services\Analytics;

use App\Enums\OrderStatus;
use App\Models\AdSpendDaily;
use App\Models\Order;
use App\Models\PageVisit;
use App\Models\ScalevPurchase;
use App\Support\TrafficSource;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Figures for the online-class analytics page over a range of days in the
 * display timezone. A sale is a paid DOKU order or a completed Scalev order;
 * a bundle bought in one DOKU checkout is one sale.
 */
class OnlineReport
{
    public readonly CarbonImmutable $from;

    public readonly CarbonImmutable $to;

    private ?Collection $salesCache = null;

    public function __construct(CarbonImmutable $from, CarbonImmutable $to)
    {
        $tz = config('app.display_timezone');
        $this->from = $from->setTimezone($tz)->startOfDay();
        $this->to = $to->setTimezone($tz)->endOfDay();
    }

    /** "7", "30", "90", "this_month" or "last_month", relative to today. */
    public static function forPreset(?string $preset): self
    {
        $today = CarbonImmutable::now(config('app.display_timezone'));

        return match ($preset) {
            '7' => new self($today->subDays(6), $today),
            '90' => new self($today->subDays(89), $today),
            'this_month' => new self($today->startOfMonth(), $today),
            'last_month' => new self($today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()),
            default => new self($today->subDays(29), $today),
        };
    }

    public function summary(): array
    {
        $visits = $this->visits();
        $visitors = (int) (clone $visits)->distinct()->count('visitor_id');
        $seconds = (int) (clone $visits)->sum('duration_seconds');
        $sales = $this->sales();
        $ads = $this->ads()->selectRaw('coalesce(sum(spend),0) as spend, coalesce(sum(link_clicks),0) as clicks')->first();
        $spend = (float) $ads->spend;
        $clicks = (int) $ads->clicks;
        $revenue = (int) $sales->sum('amount');

        return [
            'visitors' => $visitors,
            'views' => (int) (clone $visits)->count(),
            'seconds_per_visitor' => $visitors ? (int) round($seconds / $visitors) : null,
            'sales' => $sales->count(),
            'revenue' => $revenue,
            'spend' => $spend,
            'link_clicks' => $clicks,
            'cpc' => $clicks ? $spend / $clicks : null,
            'cost_per_sale' => $sales->count() && $spend > 0 ? $spend / $sales->count() : null,
            'roas' => $spend > 0 ? $revenue / $spend : null,
            'conversion' => $visitors ? $sales->count() / $visitors : null,
        ];
    }

    /** @return array<string, array{visitors: int, sales: int, revenue: int, spend: float}> keyed by Y-m-d */
    public function daily(): array
    {
        $days = collect(CarbonPeriod::create($this->from->toDateString(), $this->to->toDateString()))
            ->mapWithKeys(fn ($d) => [$d->toDateString() => ['visitors' => 0, 'sales' => 0, 'revenue' => 0, 'spend' => 0.0]])
            ->all();

        $this->visits()->selectRaw('visit_date, count(distinct visitor_id) as n')->groupBy('visit_date')->get()
            ->each(function ($row) use (&$days) {
                $key = CarbonImmutable::parse($row->visit_date)->toDateString();
                if (isset($days[$key])) {
                    $days[$key]['visitors'] = (int) $row->n;
                }
            });

        foreach ($this->sales() as $sale) {
            $key = $sale['at']->toDateString();
            if (isset($days[$key])) {
                $days[$key]['sales']++;
                $days[$key]['revenue'] += $sale['amount'];
            }
        }

        $this->ads()->selectRaw('date, sum(spend) as spend')->groupBy('date')->get()
            ->each(function ($row) use (&$days) {
                $key = CarbonImmutable::parse($row->date)->toDateString();
                if (isset($days[$key])) {
                    $days[$key]['spend'] = (float) $row->spend;
                }
            });

        return $days;
    }

    /** The most visited pages, with time on page per visitor. */
    public function pages(int $limit = 10): array
    {
        return $this->visits()
            ->selectRaw('path, count(distinct visitor_id) as visitors, count(*) as views, sum(duration_seconds) as seconds')
            ->groupBy('path')
            ->orderByDesc('visitors')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'key' => $r->path,
                'path' => $r->path,
                'visitors' => (int) $r->visitors,
                'views' => (int) $r->views,
                'seconds_per_visitor' => $r->visitors ? (int) round($r->seconds / $r->visitors) : 0,
            ])
            ->all();
    }

    /** Visitors and DOKU sales per source. Scalev orders carry no source. */
    public function sources(): array
    {
        $visitors = $this->visits()
            ->selectRaw('source, count(distinct visitor_id) as visitors')
            ->groupBy('source')
            ->pluck('visitors', 'source');

        $ownHost = TrafficSource::host(config('lms.frontend_url'));
        $sales = $this->dokuOrders()->get(['attribution'])
            ->countBy(fn (Order $o) => TrafficSource::classify((array) $o->attribution, $ownHost));

        return $visitors->keys()->merge($sales->keys())->unique()
            ->map(fn ($source) => [
                'key' => $source,
                'source' => TrafficSource::label($source),
                'visitors' => (int) ($visitors[$source] ?? 0),
                'sales' => (int) ($sales[$source] ?? 0),
                'conversion' => ($visitors[$source] ?? 0) ? ($sales[$source] ?? 0) / $visitors[$source] : null,
            ])
            ->sortByDesc('visitors')
            ->values()
            ->all();
    }

    public function campaigns(): array
    {
        return $this->ads()
            ->selectRaw('campaign_id, max(campaign_name) as name, sum(spend) as spend, sum(link_clicks) as clicks, sum(purchases) as purchases, max(currency) as currency')
            ->groupBy('campaign_id')
            ->orderByDesc('spend')
            ->get()
            ->map(fn ($r) => [
                'key' => $r->campaign_id,
                'name' => $r->name,
                'spend' => (float) $r->spend,
                'clicks' => (int) $r->clicks,
                'cpc' => $r->clicks ? $r->spend / $r->clicks : null,
                'purchases' => (int) $r->purchases,
                'cost_per_purchase' => $r->purchases ? $r->spend / $r->purchases : null,
                'currency' => $r->currency,
            ])
            ->all();
    }

    public function hasVisits(): bool
    {
        return PageVisit::query()->exists();
    }

    private function visits()
    {
        return PageVisit::query()->whereBetween('visit_date', [$this->from->toDateString(), $this->to->toDateString()]);
    }

    private function ads()
    {
        return AdSpendDaily::query()->whereBetween('date', [$this->from->toDateString(), $this->to->toDateString()]);
    }

    private function dokuOrders()
    {
        return Order::query()
            ->where('status', OrderStatus::Paid)
            ->whereBetween('paid_at', [$this->from->utc(), $this->to->utc()]);
    }

    /** @return Collection<int, array{at: CarbonImmutable, amount: int}> in the display timezone */
    private function sales(): Collection
    {
        $tz = config('app.display_timezone');

        return $this->salesCache ??= $this->dokuOrders()->get(['paid_at', 'amount'])
            ->map(fn (Order $o) => ['at' => CarbonImmutable::parse($o->paid_at)->setTimezone($tz), 'amount' => (int) $o->amount])
            ->concat(ScalevPurchase::query()
                ->whereBetween('purchased_at', [$this->from->utc(), $this->to->utc()])
                ->get(['purchased_at', 'amount'])
                ->map(fn (ScalevPurchase $p) => ['at' => CarbonImmutable::parse($p->purchased_at)->setTimezone($tz), 'amount' => (int) $p->amount]))
            ->values();
    }
}
