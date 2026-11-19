<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class Order extends Model
{
    public const PAID_VIA_NOTIFICATION = 'notification';

    public const PAID_VIA_STATUS_CHECK = 'status_check';

    protected $hidden = ['claim_hash'];

    protected $fillable = ['number', 'student_id', 'course_id', 'amount', 'status', 'gateway', 'gateway_reference', 'payment_url', 'expires_at', 'claim_hash', 'paid_at', 'paid_via', 'attribution', 'meta_conversion_sent_at'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => OrderStatus::class,
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'attribution' => 'array',
            'meta_conversion_sent_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** The courses this order pays for, main course first. */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderByRaw("CASE WHEN kind = 'main' THEN 0 ELSE 1 END")->orderBy('id');
    }

    /** Every course the order opens; an order from before bundles holds only its main course. */
    public function purchasedCourses(): Collection
    {
        $courses = $this->items->map(fn (OrderItem $item) => $item->course)->filter()->values();

        return $courses->isEmpty() ? collect([$this->course]) : $courses;
    }

    /** "Kelas A" or "Kelas A + Kelas B", for mails and messages to the buyer. */
    public function purchaseTitle(): string
    {
        return $this->purchasedCourses()->pluck('title')->implode(' + ');
    }

    public function accessDeliveries(): HasMany
    {
        return $this->hasMany(AccessDelivery::class);
    }

    /** The last WhatsApp hand-over, for the panel's status column. */
    public function latestWhatsApp(): HasOne
    {
        return $this->hasOne(AccessDelivery::class)->where('channel', 'whatsapp')->latestOfMany();
    }
}
