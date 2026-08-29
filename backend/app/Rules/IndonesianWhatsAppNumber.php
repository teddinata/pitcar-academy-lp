<?php

namespace App\Rules;

use App\Support\WhatsAppNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class IndonesianWhatsAppNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || WhatsAppNumber::normalize($value) === null) {
            $fail('Nomor WhatsApp tidak valid. Gunakan format 08xx, 62xx, atau +62xx.');
        }
    }
}
