<?php

namespace App\Exceptions;

use App\Interfaces\TransmorpherInterface;
use Exception;
use Throwable;

class InvalidTransmorpherException extends Exception
{
    public function __construct($transmorpherClass, $code = 0, Throwable $previous = null)
    {
        parent::__construct(sprintf('Configured class "%s" does not implement %s.', $transmorpherClass, TransmorpherInterface::class),
            $code,
            $previous);
    }
}
