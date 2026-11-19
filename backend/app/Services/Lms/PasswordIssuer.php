<?php

namespace App\Services\Lms;

use App\Models\Student;
use Illuminate\Support\Facades\Hash;

class PasswordIssuer
{
    // No 0/O, 1/I/L: the password is read off a screen and typed on a phone.
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * Replaces the student's password and returns the new one. Only the hash
     * is kept, so this return value is the one chance to show it.
     */
    public function issue(Student $student): string
    {
        $groups = [];

        foreach (range(1, 3) as $_) {
            $group = '';
            foreach (range(1, 4) as $__) {
                $group .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $groups[] = $group;
        }

        $password = implode('-', $groups);
        $student->forceFill(['password' => Hash::make($password), 'password_changed_at' => null])->save();

        return $password;
    }

    /** Stores a password the student chose. */
    public function set(Student $student, string $password): void
    {
        $student->forceFill(['password' => Hash::make($password), 'password_changed_at' => now()])->save();
    }

    /**
     * A chosen password must match exactly. A system-made one is also accepted
     * without regard to case or the dashes, since it is typed from memory or a note.
     */
    public function matches(Student $student, string $typed): bool
    {
        if ($student->password === null) {
            return false;
        }

        if (Hash::check($typed, $student->password)) {
            return true;
        }

        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $typed));

        return strlen($clean) === 12 && Hash::check(implode('-', str_split($clean, 4)), $student->password);
    }
}
