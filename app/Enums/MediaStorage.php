<?php

namespace App\Enums;

use Illuminate\Contracts\Filesystem\Filesystem;
use Storage;

enum MediaStorage: string
{
    case ORIGINALS = 'originals';
    case IMAGE_DERIVATIVES = 'imageDerivatives';
    case VIDEO_DERIVATIVES = 'videoDerivatives';

    /**
     * Retrieve storage disk from the value specified in the transmorpher config.
     *
     * @return Filesystem
     */
    public function getDisk(): Filesystem
    {
        return Storage::disk(config(sprintf('transmorpher.disks.%s', $this->value)));
    }

    /**
     * Returns the file path to the cache invalidation file in which the current revision is stored.
     *
     * @return string
     */
    public static function getCacheInvalidationFilePath(): string
    {
        return 'cacheInvalidationRevision';
    }
}
