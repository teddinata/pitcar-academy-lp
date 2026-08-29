<?php

namespace Tests\Unit;

use App\Support\WhatsAppNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WhatsAppNumberTest extends TestCase
{
    /** @return array<string, array{0: string, 1: ?string}> */
    public static function numbers(): array
    {
        return [
            'leading zero' => ['081234567890', '6281234567890'],
            'plus 62' => ['+6281234567890', '6281234567890'],
            'bare 62' => ['6281234567890', '6281234567890'],
            'spaces and dashes' => ['+62 812-3456-7890', '6281234567890'],
            'parentheses' => ['(0812) 3456 7890', '6281234567890'],
            'double zero prefix' => ['006281234567890', '6281234567890'],
            'missing leading zero' => ['81234567890', '6281234567890'],
            'shortest valid' => ['08123456789', '628123456789'],
            'too short' => ['0812345', null],
            'too long' => ['0812345678901234', null],
            'landline' => ['0215551234', null],
            'foreign' => ['+14155552671', null],
            'letters only' => ['telepon saya', null],
            'empty' => ['', null],
        ];
    }

    #[DataProvider('numbers')]
    public function test_it_normalises_indonesian_mobile_numbers(string $input, ?string $expected): void
    {
        $this->assertSame($expected, WhatsAppNumber::normalize($input));
    }

    public function test_it_treats_null_as_invalid(): void
    {
        $this->assertNull(WhatsAppNumber::normalize(null));
        $this->assertFalse(WhatsAppNumber::isValid(null));
    }
}
