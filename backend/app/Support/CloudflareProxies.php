<?php

namespace App\Support;

/**
 * Cloudflare's published edge ranges.
 *
 * The app sits behind Cloudflare, which terminates TLS and rewrites the
 * client address. Laravel therefore has to be told which upstreams may
 * speak for the visitor via X-Forwarded-*.
 *
 * Trusting every proxy would be simpler, but the origin has a public IP of
 * its own: anyone could reach it directly, forge X-Forwarded-For, and walk
 * straight past the per-IP lead rate limit. Naming the ranges keeps that
 * shut.
 *
 * Source: https://www.cloudflare.com/ips/ — stable for years at a time, but
 * worth re-checking if requests start arriving with a Cloudflare address as
 * their client IP.
 */
final class CloudflareProxies
{
    public const V4 = [
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
    ];

    public const V6 = [
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [...self::V4, ...self::V6];
    }
}
