<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class TransformationNotFoundException extends Exception
{
    /**
     * @param $transformationValue
     * @param $code
     * @param Throwable|null $previous
     */
    public function __construct($transformationValue, $code = 0, Throwable $previous = null)
    {
        parent::__construct(sprintf('The requested transformation %s is not an available transformation.', $transformationValue), $code, $previous);
    }
}
