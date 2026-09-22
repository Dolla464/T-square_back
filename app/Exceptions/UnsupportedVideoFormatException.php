<?php

namespace App\Exceptions;

use Exception;

class UnsupportedVideoFormatException extends Exception
{
    public function __construct(string $message = 'This video format is not supported for playback.')
    {
        parent::__construct($message);
    }
}
