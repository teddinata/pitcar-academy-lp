<?php

namespace App\Services\Lms;

use RuntimeException;

/** An add-on the buyer cannot take with this course: not offered, switched off, or already owned. */
class AddonUnavailable extends RuntimeException
{
    public function __construct(string $message, public readonly bool $owned = false)
    {
        parent::__construct($message);
    }
}
