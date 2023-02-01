<?php

namespace App\Enums;

use Illuminate\Contracts\Filesystem\Filesystem;
use Storage;

enum MediaStorage: string
{
    case ORIGINALS = 'originals';
    case IMAGE_DERIVATIVES = 'imageDerivatives';

    /**
     * Retrieve storage disk from the value specified in the transmorpher config.
     *
     * @return Filesystem
     */
    public function getDisk(): Filesystem
    {
        return Storage::disk(config('transmorpher.disks.%s'), $this->value);
    }
}
