<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PageVisit;
use App\Support\TrafficSource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Throwable;

/**
 * Page views from the public site. Answers 204 whatever happens: a visitor's
 * page must never wait on, or show, an analytics problem.
 */
class VisitController extends Controller
{
    private const BOTS = '/bot|crawl|spider|slurp|headless|lighthouse|pagespeed|preview|facebookexternalhit|whatsapp|curl|wget|python|axios|httpclient/i';

    public function start(Request $request): Response
    {
        try {
            $data = $request->json()->all();
            $id = (string) ($data['id'] ?? '');
            $visitor = (string) ($data['visitor'] ?? '');
            $path = '/'.ltrim((string) parse_url((string) ($data['path'] ?? ''), PHP_URL_PATH), '/');

            if (! Str::isUuid($id) || ! Str::isUuid($visitor) || preg_match(self::BOTS, (string) $request->userAgent())) {
                return response()->noContent();
            }

            $entry = (array) ($data['entry'] ?? []);
            $ownHost = TrafficSource::host(config('lms.frontend_url'));

            PageVisit::firstOrCreate(['id' => $id], [
                'visitor_id' => $visitor,
                'visit_date' => now(config('app.display_timezone'))->toDateString(),
                'path' => substr($path, 0, 191),
                'source' => TrafficSource::classify($entry, $ownHost),
                'referrer_host' => TrafficSource::host($entry['referrer'] ?? null),
                'utm_campaign' => filled($entry['utm_campaign'] ?? null) ? substr((string) $entry['utm_campaign'], 0, 191) : null,
                'device' => preg_match('/Mobi|Android|iPhone|iPad/i', (string) $request->userAgent()) ? 'mobile' : 'desktop',
                'started_at' => now(),
                'last_seen_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }

        return response()->noContent();
    }

    /**
     * Time on page, plus where the visitor is. "active": true is the minute
     * heartbeat of a page on screen; false means the page was closed or left;
     * no "active" (tab switched away) leaves it as it was, so a hidden tab
     * drops off "on the site now" once its last beat is two minutes old.
     */
    public function time(Request $request, string $id): Response
    {
        try {
            $visit = Str::isUuid($id) ? PageVisit::find($id) : null;

            if ($visit !== null) {
                $seconds = min(max(0, (int) ($request->json('seconds') ?? 0)), PageVisit::MAX_SECONDS);
                $visit->duration_seconds = max($visit->duration_seconds, $seconds);
                $active = $request->json('active');
                if ($active === true) {
                    $visit->last_seen_at = now();
                } elseif ($active === false) {
                    $visit->last_seen_at = null;
                }
                $visit->save();
            }
        } catch (Throwable $e) {
            report($e);
        }

        return response()->noContent();
    }
}
