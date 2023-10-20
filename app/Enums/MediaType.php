<?php

namespace App\Enums;

use App\Interfaces\MediaHandlerInterface;

enum MediaType: string
{
    case IMAGE = 'image';
    case VIDEO = 'video';

    /**
     * @return MediaHandlerInterface
     */
    public function handler(): MediaHandlerInterface
    {
        return app(config(sprintf('transmorpher.media_handlers.%s', $this->value)));
    }

    public function prefix(): string
    {
        return match ($this) {
            self::IMAGE => 'images',
            self::VIDEO => 'videos'
        };
    }
}
