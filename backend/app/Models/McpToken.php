<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class McpToken extends Model
{
    protected $fillable = ['user_id', 'name', 'token_hash', 'last_used_at'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Creates a token and returns the plain value, which is not stored. */
    public static function issue(User $user, string $name): string
    {
        $plain = 'pa_mcp_'.Str::random(40);
        self::create(['user_id' => $user->id, 'name' => $name, 'token_hash' => hash('sha256', $plain)]);

        return $plain;
    }

    public static function findPlain(?string $plain): ?self
    {
        return filled($plain) ? self::where('token_hash', hash('sha256', $plain))->first() : null;
    }
}
