<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class InvalidTransformationFormatException extends Exception
{
    /**
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($code = 0, ?Throwable $previous = null)
    {
        parent::__construct('Transformations need to follow the format {transformation}-{value}.', $code, $previous);
    }
}
