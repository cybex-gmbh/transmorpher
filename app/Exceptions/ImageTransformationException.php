<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class ImageTransformationException extends Exception
{
    protected $message = "%s. Please check if ImageMagick delegates are available for the image format.";

    public function __construct(string $message, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(sprintf($this->message, $message), $code, $previous);
    }
}
