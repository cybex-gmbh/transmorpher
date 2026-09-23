<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class ClientNotificationFailedException extends Exception
{
    /**
     * @param string $userName
     * @param string $notificationType
     * @param int $httpCode
     * @param string $reason
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $userName, string $notificationType, int $httpCode, string $reason, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(
            sprintf(
                'Failed to notify the client %s for the type %s. HTTP Code: %s, Reason: %s',
                $userName, $notificationType, $httpCode, $reason
            ), $code, $previous);
    }
}
