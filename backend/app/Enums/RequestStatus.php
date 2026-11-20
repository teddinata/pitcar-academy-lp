<?php

namespace App\Enums;

enum RequestStatus: string
{
    case New = 'new';
    case Reviewing = 'reviewing';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::Reviewing => 'Sedang ditinjau',
            self::Done => 'Selesai',
        };
    }
}
