<?php

namespace App\Enums;

use App\Interfaces\MediaHandlerInterface;
use Illuminate\Contracts\Filesystem\Filesystem;

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

    public function getDerivativesDisk(): Filesystem
    {
        return match ($this) {
            MediaType::IMAGE => MediaStorage::IMAGE_DERIVATIVES->getDisk(),
            MediaType::VIDEO => MediaStorage::VIDEO_DERIVATIVES->getDisk(),
        };
    }
}
