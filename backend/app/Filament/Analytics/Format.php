<?php

namespace App\Filament\Analytics;

/** Number formats for the analytics page, Indonesian style. */
class Format
{
    /** Readable names for the pages that sell; other paths show as they are. */
    private const PAGES = [
        '/' => 'Beranda',
        '/kelas-online/' => 'Katalog kelas online',
        '/kelas-online-ac/' => 'Kelas AC mobil',
        '/kelas-online-inspeksi/' => 'Kelas inspeksi mobil bekas',
        '/kelas-online-fundamental/' => 'Kelas fundamental',
        '/checkout/' => 'Checkout',
        '/faq/' => 'FAQ',
    ];

    public static function pageName(string $path): string
    {
        return self::PAGES[$path] ?? $path;
    }

    public static function isNamedPage(string $path): bool
    {
        return isset(self::PAGES[$path]);
    }

    public static function rupiah(?float $value): string
    {
        return $value === null ? '-' : 'Rp'.number_format(round($value), 0, ',', '.');
    }

    public static function number(int|float|null $value): string
    {
        return $value === null ? '-' : number_format($value, 0, ',', '.');
    }

    public static function percent(?float $ratio): string
    {
        return $ratio === null ? '-' : number_format($ratio * 100, 1, ',', '.').'%';
    }

    public static function duration(?int $seconds): string
    {
        if ($seconds === null) {
            return '-';
        }
        $m = intdiv($seconds, 60);
        $s = $seconds % 60;

        return $m > 0 ? "{$m} mnt {$s} dtk" : "{$s} dtk";
    }
}
