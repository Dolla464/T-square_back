<?php

namespace App\Exceptions;

use Exception;

class GoogleAccountDisconnectedException extends Exception
{
    public function __construct(string $message = 'Google storage account is disconnected.')
    {
        parent::__construct($message);
    }
}
