<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One attempt to hand a buyer their class link outside email. */
class AccessDelivery extends Model
{
    protected $fillable = ['uuid', 'student_id', 'order_id', 'course_id', 'user_id', 'channel', 'mode', 'status', 'phone', 'provider_message_id', 'attempts', 'last_error', 'sent_at'];

    protected function casts(): array
    {
        return ['attempts' => 'integer', 'sent_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'queued', 'sending' => 'Antre',
            'sent' => 'Terkirim',
            'failed' => 'Gagal',
            'skipped' => 'Tidak dikirim',
            'opened' => 'Dibuka manual',
            default => $this->status,
        };
    }
}
