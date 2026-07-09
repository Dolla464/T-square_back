<?php

namespace App\Exceptions;

use Exception;

class InvalidUploadStateException extends Exception
{
    public function __construct(string $from, string $to)
    {
        parent::__construct("Invalid upload state transition: {$from} → {$to}");
    }
}
