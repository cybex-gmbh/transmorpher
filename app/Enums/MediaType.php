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
        return match ($this) {
            MediaType::IMAGE => app(config('transmorpher.image_handler')),
            MediaType::VIDEO => app(config('transmorpher.video_handler'))
        };
    }
}
