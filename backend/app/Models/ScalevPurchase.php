<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScalevPurchase extends Model
{
    protected $primaryKey = 'order_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'order_id',
        'customer_name',
        'customer_city',
        'product_name',
        'amount',
        'purchased_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'purchased_at' => 'datetime',
        ];
    }

    /**
     * Social proof needs a first name, not a doxxed customer. Keep the first
     * token whole and truncate the rest to initials: "Budi Santoso" becomes
     * "Budi S." and a single-word name stays as-is.
     */
    public function maskedName(): string
    {
        return self::mask($this->customer_name);
    }

    /** "Budi Santoso" becomes "Budi S."; shared with checkout orders in the same feed. */
    public static function mask(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($parts) < 2) {
            return $parts[0] ?? 'Pembeli';
        }

        return $parts[0].' '.strtoupper(mb_substr($parts[1], 0, 1)).'.';
    }

    /**
     * Shape consumed by the public sales-popup endpoint: first name plus
     * when they bought. Nothing beyond this (no city, no product, no
     * order id) may leak to visitors.
     *
     * @return array{name: string, purchased_at: string}
     */
    public function toPopupData(): array
    {
        return [
            'name' => $this->maskedName(),
            'purchased_at' => $this->purchased_at->toIso8601String(),
        ];
    }
}
