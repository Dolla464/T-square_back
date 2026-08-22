<?php

namespace App\Exceptions;

use Exception;

class InvalidPlaybackTokenException extends Exception
{
    public function __construct(string $message = 'Invalid playback token.')
    {
        parent::__construct($message);
    }
}
