<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class DocumentPageDoesNotExistException extends Exception
{
    public function __construct(int $requestedPage, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(sprintf('The requested page %d does not exist for this document', $requestedPage), $code, $previous);
    }
}
