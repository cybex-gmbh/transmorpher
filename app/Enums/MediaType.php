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

    /**
     * Get the prefix used in file paths and URLs.
     *
     * @return string
     */
    public function prefix(): string
    {
        return match ($this) {
            self::IMAGE => 'images',
            self::VIDEO => 'videos'
        };
    }

    /**
     * Get whether this media is instantly available at its public path.
     *
     * @return bool
     */
    public function isInstantlyAvailable(): bool
    {
        return match ($this) {
            self::IMAGE => true,
            self::VIDEO => false
        };
    }

    /**
     * Get whether this media needs a short invalidation path for the CDN.
     *
     * @return bool
     */
    public function needsShortPathInvalidation(): bool
    {
        return match ($this) {
            self::IMAGE => true,
            self::VIDEO => false
        };
    }
}
