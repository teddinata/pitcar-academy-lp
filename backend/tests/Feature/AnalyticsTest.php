<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Analytics\AnalyticsStats;
use App\Filament\Analytics\CampaignsTable;
use App\Filament\Analytics\LiveVisitors;
use App\Filament\Analytics\PagesTable;
use App\Filament\Analytics\SourcesTable;
use App\Filament\Pages\MetaAdsSettings;
use App\Filament\Pages\OnlineAnalytics;
use App\Models\AdSpendDaily;
use App\Models\AppSetting;
use App\Models\Course;
use App\Models\Order;
use App\Models\PageVisit;
use App\Models\ScalevPurchase;
use App\Models\Student;
use App\Models\User;
use App\Services\Analytics\MetaAds;
use App\Services\Analytics\OnlineReport;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148';

    protected function setUp(): void
    {
        parent::setUp();
        config(['lms.frontend_url' => 'https://academy.pitcar.co.id', 'app.display_timezone' => 'Asia/Jakarta']);
    }

    private function visit(array $entry = [], string $path = '/kelas-online-ac/', ?string $visitor = null): string
    {
        $id = (string) Str::uuid();
        $this->withHeader('User-Agent', self::BROWSER)
            ->postJson('/api/visits', ['id' => $id, 'visitor' => $visitor ?? (string) Str::uuid(), 'path' => $path, 'entry' => $entry])
            ->assertNoContent();

        return $id;
    }

    public function test_a_page_view_is_recorded_with_its_source(): void
    {
        $id = $this->visit(['fbclid' => 'abc', 'utm_campaign' => 'ac-november', 'referrer' => 'https://l.facebook.com/']);

        $visit = PageVisit::findOrFail($id);
        $this->assertSame('meta', $visit->source);
        $this->assertSame('ac-november', $visit->utm_campaign);
        $this->assertSame('mobile', $visit->device);
        $this->assertSame('/kelas-online-ac/', $visit->path);
        $this->assertSame(now('Asia/Jakarta')->toDateString(), $visit->visit_date);
    }

    public function test_sources_are_named_from_the_first_page_of_the_visit(): void
    {
        $sources = collect([
            [['gclid' => 'x'], 'google'],
            [['referrer' => 'https://www.google.co.id/'], 'google'],
            [['utm_source' => 'ig'], 'meta'],
            [['utm_source' => 'WhatsApp'], 'whatsapp'],
            [['utm_source' => 'Newsletter Nov'], 'newsletternov'],
            [['referrer' => 'https://academy.pitcar.co.id/kelas-online/'], 'direct'],
            [['referrer' => 'https://pitcar.co.id/'], 'other'],
            [[], 'direct'],
        ])->map(fn ($case) => [PageVisit::findOrFail($this->visit($case[0]))->source, $case[1]]);

        $sources->each(fn ($pair) => $this->assertSame($pair[1], $pair[0]));
    }

    public function test_bots_and_malformed_ids_are_ignored(): void
    {
        $this->withHeader('User-Agent', 'facebookexternalhit/1.1')
            ->postJson('/api/visits', ['id' => (string) Str::uuid(), 'visitor' => (string) Str::uuid(), 'path' => '/'])
            ->assertNoContent();
        $this->withHeader('User-Agent', self::BROWSER)
            ->postJson('/api/visits', ['id' => 'not-a-uuid', 'visitor' => (string) Str::uuid(), 'path' => '/'])
            ->assertNoContent();

        $this->assertSame(0, PageVisit::count());
    }

    public function test_time_on_page_only_grows_and_is_capped(): void
    {
        $id = $this->visit();

        // The beacon is sent as plain text, so the browser skips the CORS preflight.
        $send = fn (int $s) => $this->call('POST', "/api/visits/{$id}/time", [], [], [], ['CONTENT_TYPE' => 'text/plain'], json_encode(['seconds' => $s]))->assertNoContent();

        $send(40);
        $send(25);
        $this->assertSame(40, PageVisit::find($id)->duration_seconds);

        $send(99999);
        $this->assertSame(PageVisit::MAX_SECONDS, PageVisit::find($id)->duration_seconds);
    }

    public function test_the_heartbeat_marks_a_page_open_and_hiding_it_closes_it(): void
    {
        $id = $this->visit();
        $beat = fn (array $body) => $this->call('POST', "/api/visits/{$id}/time", [], [], [], ['CONTENT_TYPE' => 'text/plain'], json_encode($body))->assertNoContent();

        $this->travel(90)->seconds();
        $beat(['seconds' => 60, 'active' => true]);
        $this->assertTrue(PageVisit::find($id)->last_seen_at->gt(now()->subSeconds(5)));

        // Tab switched away: time saved, still counted from the last beat.
        $beat(['seconds' => 70]);
        $this->assertNotNull(PageVisit::find($id)->last_seen_at);

        // Page closed: gone from "on the site now" at once.
        $beat(['seconds' => 75, 'active' => false]);
        $visit = PageVisit::find($id);
        $this->assertNull($visit->last_seen_at);
        $this->assertSame(75, $visit->duration_seconds);
    }

    public function test_on_the_site_now_counts_open_pages_only(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => UserRole::Manager, 'is_active' => true]));
        $make = fn (string $visitor, string $path, $seen) => PageVisit::create([
            'id' => (string) Str::uuid(), 'visitor_id' => $visitor, 'visit_date' => now('Asia/Jakarta')->toDateString(),
            'path' => $path, 'source' => 'direct', 'device' => 'mobile', 'started_at' => now(), 'last_seen_at' => $seen,
        ]);
        $budi = (string) Str::uuid();
        $make($budi, '/kelas-online-ac/', now()->subSeconds(30));
        $make($budi, '/checkout/', now()->subSeconds(10)); // two tabs, one person
        $make((string) Str::uuid(), '/kelas-online-ac/', now()->subSeconds(50));
        $make((string) Str::uuid(), '/', now()->subMinutes(10)); // went quiet
        $make((string) Str::uuid(), '/', null); // hid the page

        Livewire::test(LiveVisitors::class)
            ->assertSee('Sedang di web')
            ->assertSee('2 di Kelas AC mobil, 1 di Checkout')
            ->assertSee('5 halaman dibuka');
    }

    private function seedPeriod(): void
    {
        $today = CarbonImmutable::now('Asia/Jakarta');
        $visitor = (string) Str::uuid();
        foreach ([[$visitor, 120], [$visitor, 60], [(string) Str::uuid(), 30], [(string) Str::uuid(), 0]] as [$v, $s]) {
            PageVisit::create(['id' => (string) Str::uuid(), 'visitor_id' => $v, 'visit_date' => $today->toDateString(), 'path' => '/kelas-online-ac/', 'source' => 'meta', 'device' => 'mobile', 'duration_seconds' => $s, 'started_at' => now()]);
        }

        $course = Course::create(['title' => 'Kelas AC', 'slug' => 'kelas-online-ac', 'price' => 99000, 'status' => CourseStatus::Published]);
        $student = Student::create(['name' => 'Budi', 'email' => 'budi@example.test']);
        Order::create(['number' => 'PA0000000000PAID', 'student_id' => $student->id, 'course_id' => $course->id, 'amount' => 99000, 'status' => OrderStatus::Paid, 'paid_at' => now(), 'attribution' => ['fbclid' => 'abc']]);
        Order::create(['number' => 'PA00000000PENDNG', 'student_id' => $student->id, 'course_id' => $course->id, 'amount' => 99000, 'status' => OrderStatus::Pending]);
        ScalevPurchase::create(['order_id' => 'SC1', 'customer_name' => 'Sari', 'amount' => 149000, 'purchased_at' => now()]);
        // Outside the range: does not count.
        ScalevPurchase::create(['order_id' => 'SC0', 'customer_name' => 'Lama', 'amount' => 149000, 'purchased_at' => now()->subDays(40)]);

        AdSpendDaily::create(['date' => $today->toDateString(), 'campaign_id' => '1', 'campaign_name' => 'AC November', 'spend' => 100000, 'link_clicks' => 50, 'purchases' => 1]);
    }

    public function test_the_report_combines_visits_sales_and_ad_spend(): void
    {
        $this->seedPeriod();

        $s = OnlineReport::forPreset('30')->summary();

        $this->assertSame(3, $s['visitors']);
        $this->assertSame(4, $s['views']);
        $this->assertSame(70, $s['seconds_per_visitor']); // 210 seconds over 3 visitors
        $this->assertSame(2, $s['sales']); // one DOKU, one Scalev; the pending order does not count
        $this->assertSame(248000, $s['revenue']);
        $this->assertEqualsWithDelta(2000.0, $s['cpc'], 0.001);
        $this->assertEqualsWithDelta(50000.0, $s['cost_per_sale'], 0.001);
        $this->assertEqualsWithDelta(2.48, $s['roas'], 0.001);

        $today = CarbonImmutable::now('Asia/Jakarta')->toDateString();
        $daily = OnlineReport::forPreset('30')->daily();
        $this->assertCount(30, $daily);
        $this->assertSame(['visitors' => 3, 'sales' => 2, 'revenue' => 248000, 'spend' => 100000.0], $daily[$today]);

        $sources = collect(OnlineReport::forPreset('30')->sources())->keyBy('key');
        $this->assertSame(1, $sources['meta']['sales']);
    }

    public function test_meta_spend_is_pulled_per_campaign_per_day(): void
    {
        AppSetting::put(MetaAds::ACCOUNT, '1234567890');
        AppSetting::putSecret(MetaAds::TOKEN, 'EAAtoken');
        Http::fake([
            'graph.facebook.com/*/act_1234567890/insights*' => Http::sequence()->push([
                'data' => [[
                    'date_start' => '2026-11-19', 'campaign_id' => '77', 'campaign_name' => 'Inspeksi',
                    'spend' => '45000.50', 'impressions' => '9000', 'inline_link_clicks' => '30',
                    'actions' => [['action_type' => 'omni_purchase', 'value' => '2'], ['action_type' => 'purchase', 'value' => '2']],
                ]],
                'paging' => ['next' => 'https://graph.facebook.com/v21.0/next-page'],
            ]),
            'graph.facebook.com/v21.0/next-page' => Http::response(['data' => [[
                'date_start' => '2026-11-20', 'campaign_id' => '77', 'campaign_name' => 'Inspeksi', 'spend' => '1000', 'inline_link_clicks' => '1',
            ]]]),
            'graph.facebook.com/*/act_1234567890*' => Http::response(['name' => 'Pitcar Academy', 'currency' => 'IDR', 'account_status' => 1]),
        ]);

        $this->artisan('meta:sync-ads --days=2')->assertSuccessful();

        $rows = AdSpendDaily::orderBy('date')->get();
        $this->assertCount(2, $rows);
        $this->assertSame(2, $rows[0]->purchases); // counted once, not once per action name
        $this->assertSame(45000.5, $rows[0]->spend);
        $this->assertNotNull(AppSetting::get(MetaAds::SYNCED_AT));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'access_token=EAAtoken'));
    }

    public function test_an_expired_token_is_explained(): void
    {
        AppSetting::put(MetaAds::ACCOUNT, '1234567890');
        AppSetting::putSecret(MetaAds::TOKEN, 'expired');
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => 190, 'message' => 'Session has expired']], 400)]);

        $this->artisan('meta:sync-ads')->assertFailed();

        $this->assertStringContainsString('kedaluwarsa', AppSetting::get(MetaAds::LAST_ERROR));
    }

    public function test_sync_does_nothing_until_meta_is_set_up(): void
    {
        Http::fake();

        $this->artisan('meta:sync-ads')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_the_analytics_page_renders_for_managers_only(): void
    {
        Filament::setCurrentPanel('admin');
        $this->seedPeriod();

        $this->actingAs(User::factory()->create(['role' => UserRole::Consultant]));
        $this->assertFalse(OnlineAnalytics::canAccess());

        $this->actingAs(User::factory()->create(['role' => UserRole::Manager, 'is_active' => true]));
        $this->get('/admin/analitik')->assertOk()->assertSee('Analitik kelas online');
        Livewire::test(AnalyticsStats::class, ['pageFilters' => ['range' => '30']])->assertSee('Rp50.000')->assertSee('Rp248.000');
        Livewire::test(PagesTable::class, ['pageFilters' => ['range' => '30']])->assertSee('Kelas AC mobil');
        Livewire::test(SourcesTable::class, ['pageFilters' => ['range' => '30']])->assertSee('Facebook / Instagram');
        Livewire::test(CampaignsTable::class, ['pageFilters' => ['range' => '30']])->assertSee('AC November');
    }

    public function test_the_admin_connects_meta_from_the_panel(): void
    {
        Filament::setCurrentPanel('admin');
        Http::fake([
            'graph.facebook.com/*/insights*' => Http::response(['data' => []]),
            'graph.facebook.com/*' => Http::response(['name' => 'Pitcar Academy', 'currency' => 'IDR', 'account_status' => 1]),
        ]);

        $this->actingAs(User::factory()->create(['role' => UserRole::Manager, 'is_active' => true]));
        $this->assertFalse(MetaAdsSettings::canAccess());

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]));
        Livewire::test(MetaAdsSettings::class)
            ->fillForm(['account_id' => 'act_1234567890', 'token' => 'EAAsecret'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('1234567890', AppSetting::get(MetaAds::ACCOUNT));
        $this->assertNotSame('EAAsecret', AppSetting::get(MetaAds::TOKEN)); // stored encrypted
        $this->assertSame('EAAsecret', AppSetting::secret(MetaAds::TOKEN));
        $this->assertNull(AppSetting::get(MetaAds::LAST_ERROR));

        // Saving again without a token keeps the one stored.
        Livewire::test(MetaAdsSettings::class)->fillForm(['account_id' => '1234567890', 'token' => null])->call('save')->assertHasNoFormErrors();
        $this->assertSame('EAAsecret', AppSetting::secret(MetaAds::TOKEN));
    }
}
