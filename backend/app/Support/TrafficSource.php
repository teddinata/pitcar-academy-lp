<?php

namespace App\Support;

/**
 * Names where a visit or an order came from, from the first page of the
 * visit: its utm tags, ad click ids and referrer. The same rule runs on
 * page visits and on order attribution, so the two can be compared.
 */
class TrafficSource
{
    public const META = 'meta';

    public const GOOGLE = 'google';

    public const TIKTOK = 'tiktok';

    public const WHATSAPP = 'whatsapp';

    public const DIRECT = 'direct';

    public const OTHER = 'other';

    /** @param  array<string, mixed>  $p  utm_source, utm_medium, fbclid, gclid, ttclid, referrer */
    public static function classify(array $p, ?string $ownHost = null): string
    {
        $utm = strtolower(trim((string) ($p['utm_source'] ?? '')));
        $host = self::host($p['referrer'] ?? null);

        if ($host !== null && $ownHost !== null && self::sameSite($host, $ownHost)) {
            $host = null;
        }

        return match (true) {
            filled($p['fbclid'] ?? null), (bool) preg_match('/^(fb|facebook|ig|instagram|meta|an|msg)$/', $utm) => self::META,
            filled($p['gclid'] ?? null), $utm === 'google' => self::GOOGLE,
            filled($p['ttclid'] ?? null), $utm === 'tiktok' => self::TIKTOK,
            (bool) preg_match('/^(wa|whatsapp)$/', $utm) => self::WHATSAPP,
            $utm !== '' => substr(preg_replace('/[^a-z0-9_.-]/', '', $utm) ?: self::OTHER, 0, 40),
            $host === null => self::DIRECT,
            (bool) preg_match('/(^|\.)(facebook|instagram|fb|messenger)\.com$|(^|\.)fb\.me$|^l\.instagram\.com$/', $host) => self::META,
            (bool) preg_match('/(^|\.)google\./', $host) => self::GOOGLE,
            (bool) preg_match('/(^|\.)tiktok\.com$/', $host) => self::TIKTOK,
            (bool) preg_match('/(^|\.)(whatsapp\.com|wa\.me)$/', $host) => self::WHATSAPP,
            default => self::OTHER,
        };
    }

    public static function label(string $source): string
    {
        return match ($source) {
            self::META => 'Facebook / Instagram',
            self::GOOGLE => 'Google',
            self::TIKTOK => 'TikTok',
            self::WHATSAPP => 'WhatsApp',
            self::DIRECT => 'Langsung',
            self::OTHER => 'Situs lain',
            default => 'utm: '.$source,
        };
    }

    /** A second line under the label, where the name alone is unclear. */
    public static function hint(string $source): ?string
    {
        return match ($source) {
            self::DIRECT => 'ketik alamat, bookmark, atau aplikasi chat',
            self::OTHER => 'link dari situs lain',
            default => null,
        };
    }

    public static function host(?string $url): ?string
    {
        $host = strtolower((string) parse_url(trim((string) $url), PHP_URL_HOST));

        return $host === '' ? null : substr(preg_replace('/^www\./', '', $host), 0, 191);
    }

    private static function sameSite(string $host, string $own): bool
    {
        $own = preg_replace('/^www\./', '', strtolower($own));

        return $host === $own || str_ends_with($host, '.'.$own);
    }
}
