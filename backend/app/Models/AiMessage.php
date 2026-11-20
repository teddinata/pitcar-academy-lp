<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    public const PENDING = 'pending';

    public const DONE = 'done';

    public const FAILED = 'failed';

    protected $fillable = ['conversation_id', 'role', 'content', 'status', 'error', 'input_tokens', 'output_tokens', 'cache_read_tokens', 'cache_write_tokens'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
