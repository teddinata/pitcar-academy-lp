<?php

namespace App\Services\Doku;

/**
 * DOKU's non-SNAP request signature: HMAC-SHA256 over five header lines.
 * The same recipe signs our requests and proves a notification is theirs.
 */
class Signature
{
    public static function make(string $clientId, string $requestId, string $timestamp, string $target, ?string $body, string $secret): string
    {
        $lines = [
            "Client-Id:{$clientId}",
            "Request-Id:{$requestId}",
            "Request-Timestamp:{$timestamp}",
            "Request-Target:{$target}",
        ];

        // GET requests carry no body and so no Digest line.
        if ($body !== null) {
            $lines[] = 'Digest:'.base64_encode(hash('sha256', $body, true));
        }

        return 'HMACSHA256='.base64_encode(hash_hmac('sha256', implode("\n", $lines), $secret, true));
    }

    public static function matches(string $given, string $clientId, string $requestId, string $timestamp, string $target, string $body, string $secret): bool
    {
        return hash_equals(self::make($clientId, $requestId, $timestamp, $target, $body, $secret), $given);
    }
}
