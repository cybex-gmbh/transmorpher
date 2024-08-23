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
        return Storage::disk($this->getDiskName());
    }

    public function getDiskName(): string
    {
        return config(sprintf('transmorpher.disks.%s', $this->value));
    }
}
