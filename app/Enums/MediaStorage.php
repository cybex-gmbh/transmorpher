<?php

namespace App\Enums;

use Illuminate\Contracts\Filesystem\Filesystem;
use Storage;

enum MediaStorage: string
{
    case ORIGINALS = 'originals';
    case IMAGE_DERIVATIVES = 'imageDerivatives';

    public function getDisk(): Filesystem
    {
        return Storage::disk(config('transmorpher.disks.%s'), $this->value);
    }
}
