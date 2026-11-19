<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    public const MAIN = 'main';

    public const ADDON = 'addon';

    protected $fillable = ['order_id', 'course_id', 'course_offer_id', 'kind', 'price'];

    protected function casts(): array
    {
        return ['price' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
