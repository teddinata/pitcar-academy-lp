<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Consultant = 'consultant';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Manager => 'Sales Manager',
            self::Consultant => 'Education Consultant',
        };
    }

    /** Managers and admins work the whole pipeline; consultants see only theirs. */
    public function seesAllLeads(): bool
    {
        return $this !== self::Consultant;
    }

    public function managesPeople(): bool
    {
        return $this === self::Admin;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $r) => [$r->value => $r->label()])->all();
    }
}
