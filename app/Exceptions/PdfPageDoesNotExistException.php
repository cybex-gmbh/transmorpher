<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class PdfPageDoesNotExistException extends Exception
{
    /**
     * @param $requestedPage
     * @param $code
     * @param Throwable|null $previous
     */
    public function __construct($requestedPage, $code = 0, Throwable $previous = null)
    {
        parent::__construct(sprintf('The requested page %d does not exist for this document', $requestedPage), $code, $previous);
    }
}
