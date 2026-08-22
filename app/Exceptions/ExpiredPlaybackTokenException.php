<?php

namespace App\Exceptions;

use Exception;

class ExpiredPlaybackTokenException extends Exception
{
    public function __construct(string $message = 'Playback token has expired.')
    {
        parent::__construct($message);
    }
}
