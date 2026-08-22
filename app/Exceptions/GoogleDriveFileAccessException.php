<?php

namespace App\Exceptions;

use Exception;

class GoogleDriveFileAccessException extends Exception
{
    public function __construct(string $message = 'Unable to access Google Drive file.')
    {
        parent::__construct($message);
    }
}
