<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/** Key-value settings changed from the admin. Secret values are encrypted at rest. */
class AppSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::find($key)?->value ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function secret(string $key): ?string
    {
        $value = static::get($key);

        try {
            return $value === null ? null : Crypt::decryptString($value);
        } catch (DecryptException) {
            // APP_KEY changed since it was saved: treat it as unset, the admin saves it again.
            return null;
        }
    }

    public static function putSecret(string $key, ?string $value): void
    {
        static::put($key, $value === null || $value === '' ? null : Crypt::encryptString($value));
    }
}
