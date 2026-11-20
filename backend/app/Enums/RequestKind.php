<?php

namespace App\Enums;

enum RequestKind: string
{
    case Topic = 'topic';
    case Feedback = 'feedback';
    case Problem = 'problem';

    public function label(): string
    {
        return match ($this) {
            self::Topic => 'Usul materi',
            self::Feedback => 'Masukan',
            self::Problem => 'Kendala',
        };
    }
}
