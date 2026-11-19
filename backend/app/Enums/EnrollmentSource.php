<?php

namespace App\Enums;

enum EnrollmentSource: string
{
    case Doku = 'doku';
    // Buyers migrated from the Scalev checkout that this LMS replaces.
    case Scalev = 'scalev';
    case Manual = 'manual';
}
