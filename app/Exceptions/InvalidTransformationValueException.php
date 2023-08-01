<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class InvalidTransformationValueException extends Exception
{
    /**
     * @param $transformationValue
     * @param $transformationName
     * @param $code
     * @param Throwable|null $previous
     */
    public function __construct($transformationValue, $transformationName, $code = 0, Throwable $previous = null)
    {
        parent::__construct(sprintf('The provided value %s for the %s parameter is not valid.', $transformationValue, $transformationName), $code, $previous);
    }
}
