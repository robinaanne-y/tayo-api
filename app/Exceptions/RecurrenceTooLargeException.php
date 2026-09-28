<?php

namespace App\Exceptions;

use RuntimeException;

class RecurrenceTooLargeException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This recurrence would generate too many events. Shorten the range or reduce the occurrence count.');
    }
}
