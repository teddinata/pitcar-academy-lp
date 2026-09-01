<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\BuildsLeadPayload;
use Tests\TestCase;

/**
 * Production sits behind Cloudflare. These assert the two things that break
 * silently when the proxy is not trusted: leads from different people share
 * one rate-limit bucket, and generated URLs drop to http on an https page.
 */
class TrustedProxyTest extends TestCase
{
    use BuildsLeadPayload;
    use RefreshDatabase;

    /** An address inside Cloudflare's published 104.16.0.0/13 range. */
    private const EDGE = '104.16.5.9';

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_it_reads_the_visitor_address_from_a_cloudflare_forwarded_header(): void
    {
        Route::get('/testing/client-ip', fn () => response()->json(['ip' => request()->ip()]));

        $this->call('GET', '/testing/client-ip', server: [
            'REMOTE_ADDR' => self::EDGE,
            'HTTP_X_FORWARDED_FOR' => '203.0.113.44',
        ])->assertOk()->assertJson(['ip' => '203.0.113.44']);
    }

    public function test_it_ignores_a_forwarded_header_from_an_untrusted_source(): void
    {
        Route::get('/testing/client-ip', fn () => response()->json(['ip' => request()->ip()]));

        // Anyone can reach the origin IP directly and claim to be someone
        // else. That claim must not be honoured, or the per-IP rate limit
        // below becomes trivial to walk past.
        $this->call('GET', '/testing/client-ip', server: [
            'REMOTE_ADDR' => '198.51.100.7',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.44',
        ])->assertOk()->assertJson(['ip' => '198.51.100.7']);
    }

    public function test_it_treats_a_forwarded_https_request_as_secure(): void
    {
        Route::get('/testing/scheme', fn () => response()->json(['url' => url('/panel')]));

        $this->call('GET', '/testing/scheme', server: [
            'REMOTE_ADDR' => self::EDGE,
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ])->assertOk()->assertJsonPath('url', fn (string $url) => str_starts_with($url, 'https://'));
    }

    public function test_the_per_ip_rate_limit_counts_visitors_separately_behind_the_proxy(): void
    {
        config(['leads.rate_limit.per_ip' => 1, 'leads.rate_limit.per_whatsapp' => 100]);

        $post = fn (string $visitor, string $number) => $this->call(
            'POST',
            '/api/leads',
            server: [
                'REMOTE_ADDR' => self::EDGE,
                'HTTP_X_FORWARDED_FOR' => $visitor,
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode($this->leadPayload(['whatsapp_number' => $number])),
        );

        $post('203.0.113.1', '081200000001')->assertCreated();

        // Same edge server, different person. Untrusted, this would be a 429.
        $post('203.0.113.2', '081200000002')->assertCreated();

        $post('203.0.113.1', '081200000003')->assertStatus(429);
    }
}
