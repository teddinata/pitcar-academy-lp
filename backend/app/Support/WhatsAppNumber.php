<?php

namespace App\Support;

/**
 * The server is the last word on phone numbers. The browser normalises for
 * display, but everything stored, deduplicated and dialled comes from here.
 */
class WhatsAppNumber
{
    /** Returns an E.164-style Indonesian number without the plus, or null. */
    public static function normalize(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        if ($digits === '') {
            return null;
        }

        // 00 62 ... international prefix
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            // People routinely drop the leading zero.
            $digits = '62'.$digits;
        }

        return self::isValid($digits) ? $digits : null;
    }

    public static function isValid(?string $digits): bool
    {
        // 62 + operator prefix 8xx + subscriber number.
        return is_string($digits) && (bool) preg_match('/^628\d{7,12}$/', $digits);
    }
}
